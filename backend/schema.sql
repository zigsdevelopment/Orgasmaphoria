-- Orgasmaphoria production database starter (PostgreSQL / Supabase-style)
-- Review with a qualified backend developer before deployment.

create extension if not exists pgcrypto;

create type public.member_tier as enum ('listener', 'inner', 'patron', 'staff');
create type public.content_status as enum ('draft', 'published', 'archived');
create type public.content_access as enum ('public', 'listener', 'inner', 'patron', 'staff');
create type public.purchase_status as enum ('pending', 'paid', 'refunded', 'failed', 'cancelled');
create type public.report_status as enum ('open', 'reviewing', 'resolved', 'dismissed');

create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  username text not null unique check (username ~ '^[a-z0-9_.-]{3,30}$'),
  display_name text not null check (char_length(display_name) between 1 and 60),
  bio text not null default '' check (char_length(bio) <= 500),
  city text not null default '' check (char_length(city) <= 80),
  interests text[] not null default '{}',
  avatar_path text,
  role member_tier not null default 'listener',
  membership_tier member_tier not null default 'listener',
  profile_visibility text not null default 'members' check (profile_visibility in ('members', 'hidden')),
  allow_messages text not null default 'members' check (allow_messages in ('members', 'nobody')),
  show_online boolean not null default true,
  show_city boolean not null default true,
  show_interests boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.memberships (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  provider_customer_id text,
  provider_subscription_id text unique,
  tier member_tier not null,
  status text not null check (status in ('trialing', 'active', 'past_due', 'cancelled', 'expired')),
  current_period_end timestamptz,
  cancel_at_period_end boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.content_items (
  id uuid primary key default gen_random_uuid(),
  slug text not null unique,
  title text not null check (char_length(title) <= 120),
  subtitle text not null default '' check (char_length(subtitle) <= 140),
  description text not null default '' check (char_length(description) <= 2000),
  content_type text not null,
  access content_access not null default 'listener',
  status content_status not null default 'draft',
  tags text[] not null default '{}',
  cover_path text,
  published_at timestamptz,
  created_by uuid references public.profiles(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.content_files (
  id uuid primary key default gen_random_uuid(),
  content_id uuid not null references public.content_items(id) on delete cascade,
  storage_path text not null unique,
  original_filename text not null,
  mime_type text not null,
  size_bytes bigint not null check (size_bytes > 0 and size_bytes <= 26214400),
  sha256 text,
  version integer not null default 1,
  uploaded_by uuid not null references public.profiles(id),
  created_at timestamptz not null default now()
);

create table public.events (
  id uuid primary key default gen_random_uuid(),
  slug text not null unique,
  title text not null,
  description text not null default '',
  starts_at timestamptz not null,
  ends_at timestamptz not null,
  timezone text not null,
  location_label text not null,
  private_join_url text,
  access content_access not null default 'listener',
  capacity integer check (capacity is null or capacity > 0),
  status text not null default 'scheduled' check (status in ('draft', 'scheduled', 'cancelled', 'completed')),
  created_by uuid references public.profiles(id),
  created_at timestamptz not null default now()
);

create table public.event_rsvps (
  event_id uuid not null references public.events(id) on delete cascade,
  user_id uuid not null references public.profiles(id) on delete cascade,
  status text not null default 'going' check (status in ('going', 'waitlisted', 'cancelled')),
  accessibility_request text not null default '',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  primary key (event_id, user_id)
);

create table public.products (
  id uuid primary key default gen_random_uuid(),
  slug text not null unique,
  title text not null,
  description text not null default '',
  price_cents integer not null check (price_cents >= 0),
  currency char(3) not null default 'USD',
  provider_price_id text unique,
  status text not null default 'draft' check (status in ('draft', 'active', 'archived')),
  created_at timestamptz not null default now()
);

create table public.orders (
  id uuid primary key default gen_random_uuid(),
  user_id uuid references public.profiles(id) on delete set null,
  provider_checkout_id text unique,
  provider_payment_id text unique,
  email text,
  status purchase_status not null default 'pending',
  subtotal_cents integer not null default 0,
  total_cents integer not null default 0,
  currency char(3) not null default 'USD',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.order_items (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references public.orders(id) on delete cascade,
  product_id uuid not null references public.products(id),
  quantity integer not null check (quantity > 0),
  unit_price_cents integer not null check (unit_price_cents >= 0),
  content_id uuid references public.content_items(id)
);

create table public.conversations (
  id uuid primary key default gen_random_uuid(),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.conversation_members (
  conversation_id uuid not null references public.conversations(id) on delete cascade,
  user_id uuid not null references public.profiles(id) on delete cascade,
  last_read_at timestamptz,
  muted boolean not null default false,
  joined_at timestamptz not null default now(),
  primary key (conversation_id, user_id)
);

create table public.messages (
  id uuid primary key default gen_random_uuid(),
  conversation_id uuid not null references public.conversations(id) on delete cascade,
  sender_id uuid not null references public.profiles(id) on delete cascade,
  body text not null check (char_length(body) between 1 and 2000),
  edited_at timestamptz,
  deleted_at timestamptz,
  created_at timestamptz not null default now()
);

create table public.blocks (
  blocker_id uuid not null references public.profiles(id) on delete cascade,
  blocked_id uuid not null references public.profiles(id) on delete cascade,
  created_at timestamptz not null default now(),
  primary key (blocker_id, blocked_id),
  check (blocker_id <> blocked_id)
);

create table public.reports (
  id uuid primary key default gen_random_uuid(),
  reporter_id uuid not null references public.profiles(id) on delete cascade,
  target_user_id uuid references public.profiles(id) on delete set null,
  target_message_id uuid references public.messages(id) on delete set null,
  reason text not null,
  details text not null default '',
  status report_status not null default 'open',
  assigned_to uuid references public.profiles(id),
  resolution_notes text not null default '',
  created_at timestamptz not null default now(),
  resolved_at timestamptz
);

create table public.contact_messages (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  email text not null,
  topic text not null,
  subject text not null,
  message text not null,
  status text not null default 'new' check (status in ('new', 'assigned', 'closed', 'spam')),
  assigned_to uuid references public.profiles(id),
  created_at timestamptz not null default now()
);

create table public.audit_log (
  id bigint generated always as identity primary key,
  actor_id uuid references public.profiles(id),
  action text not null,
  resource_type text not null,
  resource_id text,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

-- Row-level security should be enabled and tested on every member table.
alter table public.profiles enable row level security;
alter table public.memberships enable row level security;
alter table public.content_items enable row level security;
alter table public.content_files enable row level security;
alter table public.events enable row level security;
alter table public.event_rsvps enable row level security;
alter table public.orders enable row level security;
alter table public.order_items enable row level security;
alter table public.conversations enable row level security;
alter table public.conversation_members enable row level security;
alter table public.messages enable row level security;
alter table public.blocks enable row level security;
alter table public.reports enable row level security;
alter table public.contact_messages enable row level security;
alter table public.audit_log enable row level security;

-- Example helper for comparing content access. Staff is always highest.
create or replace function public.tier_rank(value text)
returns integer
language sql
immutable
as $$
  select case value
    when 'public' then 0
    when 'listener' then 1
    when 'inner' then 2
    when 'patron' then 3
    when 'staff' then 4
    else -1
  end;
$$;

-- Example profile self-access. Add separate, carefully scoped member-directory policies.
create policy "profiles_select_self"
on public.profiles for select
using (auth.uid() = id);

create policy "profiles_update_self"
on public.profiles for update
using (auth.uid() = id)
with check (auth.uid() = id);

-- Example RSVP self-management.
create policy "rsvps_select_self"
on public.event_rsvps for select
using (auth.uid() = user_id);

create policy "rsvps_insert_self"
on public.event_rsvps for insert
with check (auth.uid() = user_id);

create policy "rsvps_update_self"
on public.event_rsvps for update
using (auth.uid() = user_id)
with check (auth.uid() = user_id);

-- Do not create broad "authenticated users can read everything" policies for messages,
-- private files, orders, contact submissions, reports, or audit logs.
-- Use membership checks, conversation membership checks, block checks, staff claims,
-- and short-lived signed storage URLs created by trusted server-side code.
