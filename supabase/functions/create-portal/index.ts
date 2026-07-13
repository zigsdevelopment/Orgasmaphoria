import Stripe from "npm:stripe";
import { createClient } from "npm:@supabase/supabase-js";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

Deno.serve(async (request) => {
  if (request.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const token = (request.headers.get("Authorization") || "").replace(/^Bearer\s+/i, "");
    if (!token) return json({ error: "Authentication required" }, 401);
    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const serviceRole = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const stripeSecret = Deno.env.get("STRIPE_SECRET_KEY")!;
    const siteUrl = (Deno.env.get("SITE_URL") || "").replace(/\/$/, "");
    const admin = createClient(supabaseUrl, serviceRole, { auth: { persistSession: false } });
    const { data: userData } = await admin.auth.getUser(token);
    const user = userData.user;
    if (!user) return json({ error: "Authentication required" }, 401);
    const { data: record } = await admin.from("billing_customers").select("stripe_customer_id").eq("user_id", user.id).maybeSingle();
    if (!record?.stripe_customer_id) return json({ error: "No billing account exists" }, 404);
    const stripe = new Stripe(stripeSecret);
    const portal = await stripe.billingPortal.sessions.create({ customer: record.stripe_customer_id, return_url: `${siteUrl}/account.html` });
    return json({ url: portal.url });
  } catch (error) {
    console.error(error);
    return json({ error: "Billing portal could not be opened" }, 500);
  }
});

function json(payload: unknown, status = 200) {
  return new Response(JSON.stringify(payload), { status, headers: { ...corsHeaders, "Content-Type": "application/json" } });
}
