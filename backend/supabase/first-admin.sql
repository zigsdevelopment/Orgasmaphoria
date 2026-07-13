-- Replace the email, then run this only after that user has created and verified an account.
-- This account becomes the protected technical administrator and cannot be changed by ordinary managers.

do $$
declare target uuid;
begin
  select id into target from auth.users where lower(email)=lower('REPLACE_WITH_ADMIN_EMAIL');
  if target is null then raise exception 'No verified account found for that email'; end if;
  update public.account_controls set protected_admin=true,account_status='active',updated_at=now() where user_id=target;
  insert into public.user_roles(user_id,role_key,assigned_by) values(target,'technical_admin',target)
  on conflict (user_id,role_key) do nothing;
end $$;
