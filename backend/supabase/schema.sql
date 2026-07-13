-- Orgasmaphoria production schema for Supabase/PostgreSQL.
-- Run in a new Supabase project, then create the first protected administrator
-- with the instructions in README.md. Review all policies before launch.

create extension if not exists pgcrypto;

create table if not exists public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  display_name text not null check (char_length(display_name) between 1 and 80),
  username text not null unique check (username ~ '^[a-z0-9_]{3,30}$'),
  bio text not null default '' check (char_length(bio) <= 800),
  interests text[] not null default '{}',
  directory_visibility text not null default 'members' check (directory_visibility in ('members','hidden')),
  allow_messages text not null default 'members' check (allow_messages in ('members','nobody')),
  show_online boolean not null default true,
  last_seen_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.account_controls (
  user_id uuid primary key references auth.users(id) on delete cascade,
  account_status text not null default 'active' check (account_status in ('active','disabled','pending')),
  protected_admin boolean not null default false,
  disabled_at timestamptz,
  disabled_by uuid references auth.users(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.roles (
  key text primary key,
  name text not null,
  rank integer not null default 0
);

create table if not exists public.permissions (
  key text primary key,
  name text not null
);

create table if not exists public.user_roles (
  user_id uuid references auth.users(id) on delete cascade,
  role_key text references public.roles(key) on delete cascade,
  assigned_by uuid references auth.users(id),
  assigned_at timestamptz not null default now(),
  primary key (user_id, role_key)
);

create table if not exists public.role_permissions (
  role_key text references public.roles(key) on delete cascade,
  permission_key text references public.permissions(key) on delete cascade,
  primary key (role_key, permission_key)
);

create table if not exists public.user_permission_overrides (
  user_id uuid references auth.users(id) on delete cascade,
  permission_key text references public.permissions(key) on delete cascade,
  allowed boolean not null,
  assigned_by uuid references auth.users(id),
  assigned_at timestamptz not null default now(),
  primary key (user_id, permission_key)
);

create table if not exists public.membership_tiers (
  slug text primary key,
  name text not null,
  level integer not null unique,
  active boolean not null default true
);

create table if not exists public.memberships (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  tier_slug text not null references public.membership_tiers(slug),
  status text not null default 'active' check (status in ('active','trialing','past_due','canceled','expired','manual')),
  source text not null default 'manual' check (source in ('manual','stripe','complimentary')),
  stripe_subscription_id text unique,
  current_period_end timestamptz,
  assigned_by uuid references auth.users(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create unique index if not exists memberships_one_current_per_user on public.memberships(user_id) where status in ('active','trialing','past_due','manual');

create table if not exists public.billing_customers (
  user_id uuid primary key references auth.users(id) on delete cascade,
  stripe_customer_id text not null unique,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.products (
  id uuid primary key default gen_random_uuid(),
  slug text not null unique,
  title text not null,
  kind text not null check (kind in ('membership','digital')),
  description text not null default '',
  price_cents integer not null check (price_cents >= 0),
  currency text not null default 'usd',
  stripe_price_id text unique,
  membership_tier text references public.membership_tiers(slug),
  active boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.orders (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  stripe_checkout_session_id text unique,
  stripe_payment_intent_id text,
  status text not null default 'paid' check (status in ('pending','paid','refunded','partially_refunded','failed')),
  total_cents integer not null default 0,
  currency text not null default 'usd',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.order_items (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references public.orders(id) on delete cascade,
  product_id uuid not null references public.products(id),
  quantity integer not null default 1 check (quantity between 1 and 10),
  unit_cents integer not null,
  created_at timestamptz not null default now(),
  unique (order_id, product_id)
);

create table if not exists public.entitlements (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  product_id uuid not null references public.products(id) on delete cascade,
  order_id uuid references public.orders(id) on delete set null,
  status text not null default 'active' check (status in ('active','revoked','refunded')),
  granted_at timestamptz not null default now(),
  revoked_at timestamptz,
  unique (user_id, product_id)
);

create table if not exists public.resources (
  id uuid primary key default gen_random_uuid(),
  title text not null,
  subtitle text not null default '',
  description text not null default '',
  content_type text not null default 'Document',
  format text not null default 'FILE',
  access_level text not null default 'listener' check (access_level in ('listener','velvet','inner','purchase','staff')),
  product_id uuid references public.products(id) on delete set null,
  storage_path text not null unique,
  tags text[] not null default '{}',
  status text not null default 'draft' check (status in ('draft','published','archived')),
  created_by uuid not null references auth.users(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.conversations (
  id uuid primary key default gen_random_uuid(),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.conversation_members (
  conversation_id uuid references public.conversations(id) on delete cascade,
  user_id uuid references auth.users(id) on delete cascade,
  joined_at timestamptz not null default now(),
  primary key (conversation_id, user_id)
);

create table if not exists public.messages (
  id uuid primary key default gen_random_uuid(),
  conversation_id uuid not null references public.conversations(id) on delete cascade,
  sender_id uuid not null references auth.users(id) on delete cascade,
  content text not null check (char_length(content) between 1 and 3000),
  created_at timestamptz not null default now(),
  edited_at timestamptz,
  deleted_at timestamptz
);

create table if not exists public.blocks (
  blocker_id uuid references auth.users(id) on delete cascade,
  blocked_id uuid references auth.users(id) on delete cascade,
  created_at timestamptz not null default now(),
  primary key (blocker_id, blocked_id),
  check (blocker_id <> blocked_id)
);

create table if not exists public.reports (
  id uuid primary key default gen_random_uuid(),
  reporter_id uuid not null references auth.users(id) on delete cascade,
  reported_user_id uuid references auth.users(id) on delete set null,
  message_id uuid references public.messages(id) on delete set null,
  reason text not null,
  details text not null default '',
  status text not null default 'open' check (status in ('open','reviewing','resolved','dismissed')),
  created_at timestamptz not null default now(),
  resolved_at timestamptz,
  resolved_by uuid references auth.users(id)
);

create table if not exists public.audit_logs (
  id bigint generated always as identity primary key,
  actor_id uuid references auth.users(id) on delete set null,
  action text not null,
  target_type text not null,
  target_id text,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

insert into public.roles(key,name,rank) values
  ('member','Member',10),
  ('staff','Staff',50),
  ('manager','Manager',80),
  ('technical_admin','Protected Technical Administrator',100)
on conflict (key) do update set name=excluded.name, rank=excluded.rank;

insert into public.permissions(key,name) values
  ('manage_accounts','Manage accounts and approvals'),
  ('manage_permissions','Manage roles and permissions'),
  ('manage_content','Manage resources and files'),
  ('manage_products','Manage products and memberships'),
  ('manage_events','Manage events and invitations'),
  ('manage_messages','Review reports and moderation'),
  ('view_orders','View orders and subscriptions'),
  ('view_audit','View security and audit records')
on conflict (key) do update set name=excluded.name;

insert into public.role_permissions(role_key,permission_key)
select 'technical_admin', key from public.permissions
on conflict do nothing;

insert into public.role_permissions(role_key,permission_key) values
  ('manager','manage_accounts'),('manager','manage_permissions'),('manager','manage_content'),
  ('manager','manage_products'),('manager','manage_events'),('manager','manage_messages'),
  ('manager','view_orders'),('manager','view_audit'),
  ('staff','manage_content'),('staff','manage_events'),('staff','manage_messages')
on conflict do nothing;

insert into public.membership_tiers(slug,name,level) values
  ('listener','Listener',1),
  ('velvet-patron','Velvet Patron',2),
  ('inner-circle','Inner Circle',3)
on conflict (slug) do update set name=excluded.name, level=excluded.level;

insert into public.products(slug,title,kind,description,price_cents,membership_tier,active) values
  ('velvet-patron','Velvet Patron Membership','membership','Expanded monthly membership',900,'velvet-patron',false),
  ('inner-circle','Inner Circle Membership','membership','Highest monthly membership',1900,'inner-circle',false),
  ('midnight-pages','Midnight Pages','digital','Guided reflection journal',900,null,false),
  ('signals-and-stories','Signals & Stories','digital','Printable conversation card collection',1200,null,false),
  ('listening-salon','The Listening Salon','digital','Complete gathering guide',1400,null,false),
  ('after-dark-invite-kit','After Dark Invitation Kit','digital','Editable digital invitation collection',800,null,false),
  ('rituals-of-connection','Rituals of Connection','digital','Creative activity guide',1500,null,false),
  ('collectors-library-one','Collector''s Library · Volume I','digital','Coordinated digital collection',3900,null,false)
on conflict (slug) do update set title=excluded.title, description=excluded.description, price_cents=excluded.price_cents, membership_tier=excluded.membership_tier;

create or replace function public.safe_username(base text, user_id uuid)
returns text language plpgsql stable as $$
declare
  candidate text := lower(regexp_replace(coalesce(base,''), '[^a-zA-Z0-9_]+', '', 'g'));
begin
  if char_length(candidate) < 3 then candidate := 'member_' || substr(replace(user_id::text,'-',''),1,8); end if;
  candidate := left(candidate,30);
  if exists(select 1 from public.profiles where username=candidate) then
    candidate := left(candidate,21) || '_' || substr(replace(user_id::text,'-',''),1,8);
  end if;
  return candidate;
end;
$$;

create or replace function public.handle_new_user()
returns trigger language plpgsql security definer set search_path=public as $$
begin
  insert into public.profiles(id,display_name,username)
  values (
    new.id,
    left(coalesce(nullif(new.raw_user_meta_data->>'display_name',''), split_part(new.email,'@',1), 'Member'),80),
    public.safe_username(coalesce(new.raw_user_meta_data->>'username',split_part(new.email,'@',1)),new.id)
  );
  insert into public.account_controls(user_id) values(new.id);
  insert into public.user_roles(user_id,role_key) values(new.id,'member');
  insert into public.memberships(user_id,tier_slug,status,source) values(new.id,'listener','active','complimentary');
  return new;
end;
$$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created after insert on auth.users for each row execute function public.handle_new_user();

create or replace function public.is_active_user(target uuid default auth.uid())
returns boolean language sql stable security definer set search_path=public as $$
  select exists(select 1 from public.account_controls where user_id=target and account_status='active');
$$;

create or replace function public.is_protected_admin(target uuid default auth.uid())
returns boolean language sql stable security definer set search_path=public as $$
  select coalesce((select protected_admin from public.account_controls where user_id=target),false);
$$;

create or replace function public.has_permission(requested_permission text)
returns boolean language sql stable security definer set search_path=public as $$
  select public.is_active_user(auth.uid()) and (
    coalesce((select allowed from public.user_permission_overrides where user_id=auth.uid() and permission_key=requested_permission),
      exists(
        select 1 from public.user_roles ur
        join public.role_permissions rp on rp.role_key=ur.role_key
        where ur.user_id=auth.uid() and rp.permission_key=requested_permission
      )
    )
  );
$$;

create or replace function public.membership_level(target uuid default auth.uid())
returns integer language sql stable security definer set search_path=public as $$
  select coalesce(max(mt.level),1)
  from public.memberships m join public.membership_tiers mt on mt.slug=m.tier_slug
  where m.user_id=target and m.status in ('active','trialing','past_due','manual');
$$;

create or replace function public.can_access_resource(resource_id uuid, target uuid default auth.uid())
returns boolean language plpgsql stable security definer set search_path=public as $$
declare r public.resources%rowtype;
begin
  if target is null or not public.is_active_user(target) then return false; end if;
  select * into r from public.resources where id=resource_id and status='published';
  if not found then return false; end if;
  if public.has_permission('manage_content') then return true; end if;
  if r.access_level='listener' then return true; end if;
  if r.access_level='velvet' then return public.membership_level(target)>=2; end if;
  if r.access_level='inner' then return public.membership_level(target)>=3; end if;
  if r.access_level='staff' then return false; end if;
  if r.access_level='purchase' then
    return exists(select 1 from public.entitlements where user_id=target and product_id=r.product_id and status='active');
  end if;
  return false;
end;
$$;

create or replace view public.current_membership with (security_invoker=true) as
select m.id,m.user_id,m.tier_slug,m.status,m.source,m.current_period_end,m.updated_at
from public.memberships m
where m.user_id=auth.uid() and m.status in ('active','trialing','past_due','manual')
order by (select level from public.membership_tiers where slug=m.tier_slug) desc
limit 1;

create or replace view public.member_directory with (security_invoker=true) as
select id,display_name,username,bio,interests,allow_messages,show_online,last_seen_at
from public.profiles
where directory_visibility='members' and public.is_active_user(id);

create or replace function public.start_conversation(other_user_id uuid)
returns uuid language plpgsql security definer set search_path=public as $$
declare existing_id uuid; new_id uuid;
begin
  if auth.uid() is null or other_user_id=auth.uid() then raise exception 'Invalid conversation'; end if;
  if not public.is_active_user(auth.uid()) or not public.is_active_user(other_user_id) then raise exception 'Account unavailable'; end if;
  if exists(select 1 from public.blocks where (blocker_id=auth.uid() and blocked_id=other_user_id) or (blocker_id=other_user_id and blocked_id=auth.uid())) then raise exception 'Conversation unavailable'; end if;
  if not exists(select 1 from public.profiles where id=other_user_id and directory_visibility='members' and allow_messages='members') then raise exception 'Messages are not accepted'; end if;
  select c.id into existing_id from public.conversations c
  where (select count(*) from public.conversation_members cm where cm.conversation_id=c.id)=2
    and exists(select 1 from public.conversation_members where conversation_id=c.id and user_id=auth.uid())
    and exists(select 1 from public.conversation_members where conversation_id=c.id and user_id=other_user_id)
  limit 1;
  if existing_id is not null then return existing_id; end if;
  insert into public.conversations default values returning id into new_id;
  insert into public.conversation_members(conversation_id,user_id) values(new_id,auth.uid()),(new_id,other_user_id);
  return new_id;
end;
$$;

create or replace function public.get_my_conversations()
returns table(conversation_id uuid,other_user_id uuid,other_display_name text,other_username text,last_message text,last_message_at timestamptz)
language sql stable security definer set search_path=public as $$
  select c.id, other.user_id, p.display_name, p.username,
    (select m.content from public.messages m where m.conversation_id=c.id and m.deleted_at is null order by m.created_at desc limit 1),
    (select m.created_at from public.messages m where m.conversation_id=c.id and m.deleted_at is null order by m.created_at desc limit 1)
  from public.conversations c
  join public.conversation_members mine on mine.conversation_id=c.id and mine.user_id=auth.uid()
  join public.conversation_members other on other.conversation_id=c.id and other.user_id<>auth.uid()
  join public.profiles p on p.id=other.user_id
  order by coalesce((select max(m.created_at) from public.messages m where m.conversation_id=c.id),c.created_at) desc;
$$;

create or replace function public.admin_list_accounts()
returns table(id uuid,email text,display_name text,account_status text,protected_admin boolean,tier_slug text,role_name text)
language plpgsql security definer set search_path=public,auth as $$
begin
  if not public.has_permission('manage_accounts') then raise exception 'Not authorized'; end if;
  return query
  select u.id,u.email,p.display_name,ac.account_status,ac.protected_admin,
    coalesce((select m.tier_slug from public.memberships m join public.membership_tiers mt on mt.slug=m.tier_slug where m.user_id=u.id and m.status in ('active','trialing','past_due','manual') order by mt.level desc limit 1),'listener'),
    coalesce((select r.name from public.user_roles ur join public.roles r on r.key=ur.role_key where ur.user_id=u.id order by r.rank desc limit 1),'Member')
  from auth.users u join public.profiles p on p.id=u.id join public.account_controls ac on ac.user_id=u.id
  order by ac.protected_admin desc,p.display_name;
end;
$$;

create or replace function public.admin_set_membership(target_user uuid, requested_tier text)
returns void language plpgsql security definer set search_path=public as $$
begin
  if not public.has_permission('manage_accounts') then raise exception 'Not authorized'; end if;
  if public.is_protected_admin(target_user) and target_user<>auth.uid() then raise exception 'Protected account'; end if;
  if not exists(select 1 from public.membership_tiers where slug=requested_tier) then raise exception 'Invalid tier'; end if;
  update public.memberships set status='expired',updated_at=now() where user_id=target_user and status in ('active','trialing','past_due','manual');
  insert into public.memberships(user_id,tier_slug,status,source,assigned_by) values(target_user,requested_tier,'manual','manual',auth.uid());
  insert into public.audit_logs(actor_id,action,target_type,target_id,metadata) values(auth.uid(),'membership.changed','user',target_user::text,jsonb_build_object('tier',requested_tier));
end;
$$;

create or replace function public.admin_set_account_status(target_user uuid, requested_status text)
returns void language plpgsql security definer set search_path=public as $$
begin
  if not public.has_permission('manage_accounts') then raise exception 'Not authorized'; end if;
  if requested_status not in ('active','disabled','pending') then raise exception 'Invalid status'; end if;
  if public.is_protected_admin(target_user) then raise exception 'Protected account'; end if;
  update public.account_controls set account_status=requested_status,disabled_at=case when requested_status='disabled' then now() else null end,disabled_by=case when requested_status='disabled' then auth.uid() else null end,updated_at=now() where user_id=target_user;
  insert into public.audit_logs(actor_id,action,target_type,target_id,metadata) values(auth.uid(),'account.status_changed','user',target_user::text,jsonb_build_object('status',requested_status));
end;
$$;

create or replace function public.admin_get_permissions(target_user uuid)
returns table(permission_key text,allowed boolean) language plpgsql security definer set search_path=public as $$
begin
  if not public.has_permission('manage_permissions') then raise exception 'Not authorized'; end if;
  return query select p.key,coalesce((select upo.allowed from public.user_permission_overrides upo where upo.user_id=target_user and upo.permission_key=p.key),exists(select 1 from public.user_roles ur join public.role_permissions rp on rp.role_key=ur.role_key where ur.user_id=target_user and rp.permission_key=p.key)) from public.permissions p order by p.name;
end;
$$;

create or replace function public.admin_replace_permissions(target_user uuid, permission_values jsonb)
returns void language plpgsql security definer set search_path=public as $$
declare item jsonb;
begin
  if not public.has_permission('manage_permissions') then raise exception 'Not authorized'; end if;
  if public.is_protected_admin(target_user) then raise exception 'Protected account'; end if;
  delete from public.user_permission_overrides where user_id=target_user;
  for item in select * from jsonb_array_elements(permission_values) loop
    if exists(select 1 from public.permissions where key=item->>'permission_key') then
      insert into public.user_permission_overrides(user_id,permission_key,allowed,assigned_by) values(target_user,item->>'permission_key',coalesce((item->>'allowed')::boolean,false),auth.uid());
    end if;
  end loop;
  insert into public.audit_logs(actor_id,action,target_type,target_id) values(auth.uid(),'permissions.replaced','user',target_user::text);
end;
$$;

create or replace function public.admin_list_orders()
returns table(id uuid,email text,display_name text,total_cents integer,status text,created_at timestamptz)
language plpgsql security definer set search_path=public,auth as $$
begin
  if not public.has_permission('view_orders') then raise exception 'Not authorized'; end if;
  return query select o.id,u.email,p.display_name,o.total_cents,o.status,o.created_at from public.orders o join auth.users u on u.id=o.user_id join public.profiles p on p.id=o.user_id order by o.created_at desc limit 250;
end;
$$;

alter table public.profiles enable row level security;
alter table public.account_controls enable row level security;
alter table public.user_roles enable row level security;
alter table public.user_permission_overrides enable row level security;
alter table public.memberships enable row level security;
alter table public.billing_customers enable row level security;
alter table public.products enable row level security;
alter table public.orders enable row level security;
alter table public.order_items enable row level security;
alter table public.entitlements enable row level security;
alter table public.resources enable row level security;
alter table public.conversations enable row level security;
alter table public.conversation_members enable row level security;
alter table public.messages enable row level security;
alter table public.blocks enable row level security;
alter table public.reports enable row level security;
alter table public.audit_logs enable row level security;

create policy profiles_own_update on public.profiles for update to authenticated using (id=auth.uid() and public.is_active_user()) with check (id=auth.uid());
create policy profiles_member_read on public.profiles for select to authenticated using (public.is_active_user() and (id=auth.uid() or directory_visibility='members' or public.has_permission('manage_accounts')));
create policy controls_own_read on public.account_controls for select to authenticated using (user_id=auth.uid() or public.has_permission('manage_accounts'));
create policy roles_own_read on public.user_roles for select to authenticated using (user_id=auth.uid() or public.has_permission('manage_permissions'));
create policy overrides_own_read on public.user_permission_overrides for select to authenticated using (user_id=auth.uid() or public.has_permission('manage_permissions'));
create policy memberships_own_read on public.memberships for select to authenticated using (user_id=auth.uid() or public.has_permission('manage_accounts'));
create policy billing_own_read on public.billing_customers for select to authenticated using (user_id=auth.uid() or public.has_permission('view_orders'));
create policy products_public_read on public.products for select to anon,authenticated using (active=true or public.has_permission('manage_products'));
create policy orders_own_read on public.orders for select to authenticated using (user_id=auth.uid() or public.has_permission('view_orders'));
create policy order_items_own_read on public.order_items for select to authenticated using (exists(select 1 from public.orders o where o.id=order_id and (o.user_id=auth.uid() or public.has_permission('view_orders'))));
create policy entitlements_own_read on public.entitlements for select to authenticated using (user_id=auth.uid() or public.has_permission('manage_accounts'));
create policy resources_access_read on public.resources for select to authenticated using (public.can_access_resource(id) or public.has_permission('manage_content'));
create policy resources_staff_insert on public.resources for insert to authenticated with check (public.has_permission('manage_content') and created_by=auth.uid());
create policy resources_staff_update on public.resources for update to authenticated using (public.has_permission('manage_content')) with check (public.has_permission('manage_content'));
create policy conversations_member_read on public.conversations for select to authenticated using (exists(select 1 from public.conversation_members cm where cm.conversation_id=id and cm.user_id=auth.uid()));
create policy conversation_members_read on public.conversation_members for select to authenticated using (exists(select 1 from public.conversation_members mine where mine.conversation_id=conversation_id and mine.user_id=auth.uid()));
create policy messages_member_read on public.messages for select to authenticated using (exists(select 1 from public.conversation_members cm where cm.conversation_id=conversation_id and cm.user_id=auth.uid()));
create policy messages_member_insert on public.messages for insert to authenticated with check (sender_id=auth.uid() and public.is_active_user() and exists(select 1 from public.conversation_members cm where cm.conversation_id=conversation_id and cm.user_id=auth.uid()));
create policy blocks_own_manage on public.blocks for all to authenticated using (blocker_id=auth.uid()) with check (blocker_id=auth.uid());
create policy reports_own_insert on public.reports for insert to authenticated with check (reporter_id=auth.uid());
create policy reports_staff_read on public.reports for select to authenticated using (reporter_id=auth.uid() or public.has_permission('manage_messages'));
create policy audit_staff_read on public.audit_logs for select to authenticated using (public.has_permission('view_audit'));

insert into storage.buckets(id,name,public,file_size_limit,allowed_mime_types)
values ('member-files','member-files',false,26214400,array['application/pdf','application/epub+zip','application/vnd.openxmlformats-officedocument.wordprocessingml.document','text/plain','image/png','image/jpeg','image/webp','application/zip'])
on conflict (id) do update set public=false,file_size_limit=excluded.file_size_limit,allowed_mime_types=excluded.allowed_mime_types;

create policy member_files_authorized_read on storage.objects for select to authenticated using (
  bucket_id='member-files' and exists(select 1 from public.resources r where r.storage_path=name and (public.can_access_resource(r.id) or public.has_permission('manage_content')))
);
create policy member_files_staff_insert on storage.objects for insert to authenticated with check (bucket_id='member-files' and public.has_permission('manage_content'));
create policy member_files_staff_update on storage.objects for update to authenticated using (bucket_id='member-files' and public.has_permission('manage_content')) with check (bucket_id='member-files' and public.has_permission('manage_content'));
create policy member_files_staff_delete on storage.objects for delete to authenticated using (bucket_id='member-files' and public.has_permission('manage_content'));

grant usage on schema public to anon,authenticated;
grant select on public.products to anon,authenticated;
grant select,update on public.profiles to authenticated;
grant select on public.account_controls,public.user_roles,public.user_permission_overrides,public.memberships,public.billing_customers,public.orders,public.order_items,public.entitlements,public.conversations,public.conversation_members,public.audit_logs to authenticated;
grant select,insert,update on public.resources to authenticated;
grant select,insert on public.messages,public.blocks,public.reports to authenticated;
grant delete,update on public.blocks to authenticated;
grant select on public.current_membership,public.member_directory to authenticated;
grant execute on function public.has_permission(text),public.start_conversation(uuid),public.get_my_conversations(),public.admin_list_accounts(),public.admin_set_membership(uuid,text),public.admin_set_account_status(uuid,text),public.admin_get_permissions(uuid),public.admin_replace_permissions(uuid,jsonb),public.admin_list_orders() to authenticated;
