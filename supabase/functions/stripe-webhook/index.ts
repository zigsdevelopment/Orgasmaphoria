import Stripe from "npm:stripe";
import { createClient } from "npm:@supabase/supabase-js";

Deno.serve(async (request) => {
  try {
    const stripeSecret = Deno.env.get("STRIPE_SECRET_KEY")!;
    const webhookSecret = Deno.env.get("STRIPE_WEBHOOK_SECRET")!;
    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const serviceRole = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    if (!stripeSecret || !webhookSecret || !supabaseUrl || !serviceRole) throw new Error("Server configuration is incomplete");

    const stripe = new Stripe(stripeSecret);
    const signature = request.headers.get("stripe-signature");
    if (!signature) return new Response("Missing signature", { status: 400 });
    const payload = await request.text();
    const event = await stripe.webhooks.constructEventAsync(payload, signature, webhookSecret);
    const admin = createClient(supabaseUrl, serviceRole, { auth: { persistSession: false } });

    if (event.type === "checkout.session.completed") {
      const checkout = event.data.object as Stripe.Checkout.Session;
      const userId = checkout.client_reference_id || checkout.metadata?.user_id;
      if (userId) {
        const customerId = typeof checkout.customer === "string" ? checkout.customer : checkout.customer?.id;
        if (customerId) await admin.from("billing_customers").upsert({ user_id: userId, stripe_customer_id: customerId, updated_at: new Date().toISOString() }, { onConflict: "user_id" });

        if (checkout.mode === "payment" && checkout.payment_status === "paid") {
          const lines = await stripe.checkout.sessions.listLineItems(checkout.id, { limit: 100 });
          const total = checkout.amount_total || 0;
          const { data: order, error: orderError } = await admin.from("orders").upsert({
            user_id: userId,
            stripe_checkout_session_id: checkout.id,
            stripe_payment_intent_id: typeof checkout.payment_intent === "string" ? checkout.payment_intent : null,
            status: "paid",
            total_cents: total,
            currency: checkout.currency || "usd",
            updated_at: new Date().toISOString(),
          }, { onConflict: "stripe_checkout_session_id" }).select("id").single();
          if (orderError) throw orderError;
          for (const line of lines.data) {
            const priceId = typeof line.price === "string" ? line.price : line.price?.id;
            if (!priceId) continue;
            const { data: product } = await admin.from("products").select("id,price_cents").eq("stripe_price_id", priceId).maybeSingle();
            if (!product) continue;
            await admin.from("order_items").upsert({ order_id: order.id, product_id: product.id, quantity: line.quantity || 1, unit_cents: line.amount_subtotal / Math.max(1, line.quantity || 1) }, { onConflict: "order_id,product_id" });
            await admin.from("entitlements").upsert({ user_id: userId, product_id: product.id, order_id: order.id, status: "active", revoked_at: null }, { onConflict: "user_id,product_id" });
          }
        }

        if (checkout.mode === "subscription" && checkout.subscription) {
          const subscription = await stripe.subscriptions.retrieve(typeof checkout.subscription === "string" ? checkout.subscription : checkout.subscription.id);
          await syncSubscription(admin, subscription, userId);
        }
      }
    }

    if (event.type === "customer.subscription.updated" || event.type === "customer.subscription.created") {
      const subscription = event.data.object as Stripe.Subscription;
      const userId = subscription.metadata?.user_id;
      if (userId) await syncSubscription(admin, subscription, userId);
    }

    if (event.type === "customer.subscription.deleted") {
      const subscription = event.data.object as Stripe.Subscription;
      await admin.from("memberships").update({ status: "canceled", updated_at: new Date().toISOString() }).eq("stripe_subscription_id", subscription.id);
    }

    if (event.type === "charge.refunded") {
      const charge = event.data.object as Stripe.Charge;
      const paymentIntent = typeof charge.payment_intent === "string" ? charge.payment_intent : charge.payment_intent?.id;
      if (paymentIntent) {
        const { data: order } = await admin.from("orders").update({ status: charge.amount_refunded >= charge.amount ? "refunded" : "partially_refunded", updated_at: new Date().toISOString() }).eq("stripe_payment_intent_id", paymentIntent).select("id").maybeSingle();
        if (order?.id && charge.amount_refunded >= charge.amount) await admin.from("entitlements").update({ status: "refunded", revoked_at: new Date().toISOString() }).eq("order_id", order.id);
      }
    }

    return new Response("ok", { status: 200 });
  } catch (error) {
    console.error(error);
    return new Response("Webhook error", { status: 400 });
  }
});

async function syncSubscription(admin: ReturnType<typeof createClient>, subscription: Stripe.Subscription, userId: string) {
  const priceId = subscription.items.data[0]?.price?.id;
  if (!priceId) return;
  const { data: product } = await admin.from("products").select("membership_tier").eq("stripe_price_id", priceId).eq("kind", "membership").maybeSingle();
  if (!product?.membership_tier) return;
  const statusMap: Record<string,string> = { active: "active", trialing: "trialing", past_due: "past_due", canceled: "canceled", unpaid: "past_due", incomplete: "past_due", incomplete_expired: "expired", paused: "past_due" };
  const mapped = statusMap[subscription.status] || "past_due";
  if (["active","trialing","past_due"].includes(mapped)) {
    await admin.from("memberships").update({ status: "expired", updated_at: new Date().toISOString() }).eq("user_id", userId).in("status", ["active","trialing","past_due","manual"]);
  }
  await admin.from("memberships").upsert({
    user_id: userId,
    tier_slug: product.membership_tier,
    status: mapped,
    source: "stripe",
    stripe_subscription_id: subscription.id,
    current_period_end: subscription.items.data[0]?.current_period_end ? new Date(subscription.items.data[0].current_period_end * 1000).toISOString() : null,
    updated_at: new Date().toISOString(),
  }, { onConflict: "stripe_subscription_id" });
}
