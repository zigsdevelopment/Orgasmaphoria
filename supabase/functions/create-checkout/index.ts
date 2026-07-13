import Stripe from "npm:stripe";
import { createClient } from "npm:@supabase/supabase-js";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

Deno.serve(async (request) => {
  if (request.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const authHeader = request.headers.get("Authorization") || "";
    const token = authHeader.replace(/^Bearer\s+/i, "");
    if (!token) return json({ error: "Authentication required" }, 401);

    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const serviceRole = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const stripeSecret = Deno.env.get("STRIPE_SECRET_KEY")!;
    const siteUrl = (Deno.env.get("SITE_URL") || "").replace(/\/$/, "");
    if (!supabaseUrl || !serviceRole || !stripeSecret || !siteUrl) throw new Error("Server configuration is incomplete");

    const admin = createClient(supabaseUrl, serviceRole, { auth: { persistSession: false } });
    const { data: userData, error: userError } = await admin.auth.getUser(token);
    const user = userData.user;
    if (userError || !user) return json({ error: "Authentication required" }, 401);

    const body = await request.json();
    const stripe = new Stripe(stripeSecret);
    let mode: Stripe.Checkout.SessionCreateParams.Mode = "payment";
    const lineItems: Stripe.Checkout.SessionCreateParams.LineItem[] = [];
    const metadata: Record<string, string> = { user_id: user.id, kind: String(body.kind || "products") };
    let subscriptionData: Stripe.Checkout.SessionCreateParams.SubscriptionData | undefined;

    if (body.kind === "membership") {
      const slug = String(body.slug || "");
      const { data: product, error } = await admin.from("products").select("slug,stripe_price_id,membership_tier,active").eq("slug", slug).eq("kind", "membership").maybeSingle();
      if (error || !product?.active || !product.stripe_price_id || !product.membership_tier) return json({ error: "Membership checkout is not available" }, 400);
      mode = "subscription";
      lineItems.push({ price: product.stripe_price_id, quantity: 1 });
      metadata.product_slug = product.slug;
      metadata.tier_slug = product.membership_tier;
      subscriptionData = { metadata: { user_id: user.id, tier_slug: product.membership_tier, product_slug: product.slug } };
    } else {
      const requested = Array.isArray(body.items) ? body.items.slice(0, 20) : [];
      if (!requested.length) return json({ error: "The bag is empty" }, 400);
      const slugs = [...new Set(requested.map((item: { slug?: unknown }) => String(item.slug || "")).filter(Boolean))];
      const { data: products, error } = await admin.from("products").select("id,slug,stripe_price_id,active").in("slug", slugs).eq("kind", "digital");
      if (error) throw error;
      const bySlug = new Map((products || []).map((product) => [product.slug, product]));
      for (const item of requested) {
        const product = bySlug.get(String(item.slug || ""));
        const quantity = Math.max(1, Math.min(10, Number(item.quantity) || 1));
        if (!product?.active || !product.stripe_price_id) return json({ error: "One or more products are not available" }, 400);
        lineItems.push({ price: product.stripe_price_id, quantity });
      }
      metadata.product_slugs = slugs.join(",").slice(0, 500);
    }

    const { data: customerRecord } = await admin.from("billing_customers").select("stripe_customer_id").eq("user_id", user.id).maybeSingle();
    const session = await stripe.checkout.sessions.create({
      mode,
      line_items: lineItems,
      customer: customerRecord?.stripe_customer_id || undefined,
      customer_email: customerRecord?.stripe_customer_id ? undefined : (user.email || undefined),
      client_reference_id: user.id,
      metadata,
      subscription_data: subscriptionData,
      allow_promotion_codes: true,
      billing_address_collection: "auto",
      success_url: `${siteUrl}/checkout-success.html?session_id={CHECKOUT_SESSION_ID}`,
      cancel_url: `${siteUrl}/checkout-cancel.html`,
    });

    return json({ url: session.url });
  } catch (error) {
    console.error(error);
    return json({ error: "Checkout could not be started" }, 500);
  }
});

function json(payload: unknown, status = 200) {
  return new Response(JSON.stringify(payload), { status, headers: { ...corsHeaders, "Content-Type": "application/json" } });
}
