<?php
/**
 * ORGASMAPHORIA SERVER CONFIGURATION
 * ---------------------------------
 * Keep this file private. On production hosting, prefer environment variables
 * for mail and Stripe secrets. Never place secret keys in browser JavaScript.
 */
declare(strict_types=1);

const SITE_NAME = 'Orgasmaphoria';
const SITE_TIMEZONE = 'America/New_York';
const SITE_BASE_PATH = '';
const CONTACT_RECIPIENT = '';
const CONTACT_FROM = 'no-reply@localhost';
const CONTACT_RESPONSE_TIME = 'Please allow up to two business days for a response.';
const TWO_FACTOR_ISSUER = 'Orgasmaphoria';
const TWO_FACTOR_SESSION_TTL = 43200; // 12 hours
const TWO_FACTOR_RECOVERY_CODE_COUNT = 8;
const PASSWORD_RESET_TTL = 3600; // 1 hour
const MAX_RESOURCE_SIZE = 26214400; // 25 MB
const SESSION_NAME = 'orgasmaphoria_session';

/** Public product catalog. Prices are server-authoritative. */
function product_catalog(): array
{
    return [
        'midnight-pages' => [
            'title' => 'Midnight Pages',
            'kind' => 'product',
            'priceCents' => 900,
            'description' => 'A guided reflection journal.',
        ],
        'signals-and-stories' => [
            'title' => 'Signals & Stories',
            'kind' => 'product',
            'priceCents' => 1200,
            'description' => 'A printable conversation card collection.',
        ],
        'listening-salon' => [
            'title' => 'The Listening Salon',
            'kind' => 'product',
            'priceCents' => 1400,
            'description' => 'A complete gathering guide.',
        ],
        'after-dark-invite-kit' => [
            'title' => 'After Dark Invitation Kit',
            'kind' => 'product',
            'priceCents' => 800,
            'description' => 'Editable digital invitation designs.',
        ],
        'rituals-of-connection' => [
            'title' => 'Rituals of Connection',
            'kind' => 'product',
            'priceCents' => 1500,
            'description' => 'Creative communication-centered activities.',
        ],
        'collectors-library-one' => [
            'title' => "Collector's Library · Volume I",
            'kind' => 'product',
            'priceCents' => 3900,
            'description' => 'A coordinated digital collector collection.',
        ],
        'velvet-patron' => [
            'title' => 'Velvet Patron Membership',
            'kind' => 'membership',
            'tier' => 'velvet-patron',
            'priceCents' => 900,
            'interval' => 'month',
            'description' => 'Expanded member access.',
        ],
        'inner-circle' => [
            'title' => 'Inner Circle Membership',
            'kind' => 'membership',
            'tier' => 'inner-circle',
            'priceCents' => 1900,
            'interval' => 'month',
            'description' => 'The highest Orgasmaphoria membership level.',
        ],
    ];
}

function membership_levels(): array
{
    return [
        'listener' => 1,
        'velvet-patron' => 2,
        'inner-circle' => 3,
        'staff' => 99,
    ];
}

function permission_catalog(): array
{
    return [
        'manage_accounts' => 'Manage accounts and approvals',
        'manage_permissions' => 'Manage roles and permissions',
        'manage_content' => 'Manage resources and files',
        'manage_products' => 'Manage products and memberships',
        'manage_events' => 'Manage events and invitations',
        'manage_orders' => 'View orders and subscriptions',
        'manage_messages' => 'Review moderation reports',
        'view_contacts' => 'View contact submissions',
        'view_audit' => 'View security and audit records',
    ];
}

function stripe_secret_key(): string
{
    return trim((string)(getenv('ORG_STRIPE_SECRET_KEY') ?: ''));
}

function stripe_webhook_secret(): string
{
    return trim((string)(getenv('ORG_STRIPE_WEBHOOK_SECRET') ?: ''));
}

function configured_contact_recipient(): string
{
    $environment = trim((string)(getenv('ORG_CONTACT_RECIPIENT') ?: ''));
    return $environment !== '' ? $environment : CONTACT_RECIPIENT;
}
