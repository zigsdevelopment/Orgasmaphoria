/*
  Production configuration.
  Copy the public Supabase URL and anon key from the Supabase project settings.
  Never place the service-role key, Stripe secret key, or webhook secret here.
*/
window.ORG_CONFIG = Object.freeze({
  supabaseUrl: "",
  supabaseAnonKey: "",
  checkoutFunction: "create-checkout",
  portalFunction: "create-portal",
  siteUrl: "",
  contactEndpoint: "",
  currency: "USD"
});
