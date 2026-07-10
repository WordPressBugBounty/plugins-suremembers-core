# SureMembers — WordPress Abilities (AI / MCP)

SureMembers exposes its membership operations as **WordPress Abilities** so AI
agents and MCP clients (Claude, Cursor, VS Code, etc.) can discover and call
them. The focus is **analytics and member/membership queries**, with basic
management actions gated behind admin toggles.

---

## Quick start

1. **WP Admin → SureMembers → Settings → AI Abilities**
   - Turn on **Enable Abilities** (master switch).
   - Optionally enable **Edit Abilities** (grants/revokes/create) and
     **Delete Abilities** (irreversible removals).
   - Optionally enable **MCP Server** (requires the free
     [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin) and follow
     **Connect Your AI Client** to wire up Claude/Cursor/VS Code/etc.
2. Or via WP-CLI:
   ```bash
   wp suremembers abilities enable --with-edit          # read + edit
   wp suremembers abilities enable --with-edit --with-delete
   wp suremembers abilities disable
   wp suremembers abilities status
   ```

When the master toggle is **off**, no abilities are registered and clients
cannot call anything.

---

## Naming & category

- Ability names are namespaced: **`suremembers/{ability-id}`** (lowercase).
- All abilities register under a single WP Abilities category: **`suremembers`**.
- Pro abilities use the same `suremembers/` prefix and category, so a single MCP
  server exposes Core + Pro together.

---

## Abilities

### Core (free)

**Analytics & queries — read-only (`readOnlyHint: true`):**

| Ability | Purpose | Key params |
|---|---|---|
| `suremembers/get-membership-overview` | Site KPIs: total/published/draft access groups, active members, total active grants | — |
| `suremembers/list-memberships` | All access groups + active member count, status, expiration, restriction flag | `include_drafts` |
| `suremembers/get-membership-stats` | Per-group active/revoked counts + integration breakdown | `membership_id` |
| `suremembers/list-members` | Paginated members with membership data; filter by search/role/membership | `page`, `per_page`, `search`, `roles`, `memberships` |
| `suremembers/get-member-details` | One user's full membership profile (status, dates, integration, expiry) | `user_id` |
| `suremembers/find-expiring-memberships` | Members expiring within N days | `days`, `membership_id` |
| `suremembers/get-members-by-status` | Members by status: active / revoked / expired | `status`, `membership_id`, `limit` |
| `suremembers/get-integration-breakdown` | Active grants grouped by integration source | — |

**Membership management — mutations (gated):**

| Ability | Gate | Annotation | Wraps |
|---|---|---|---|
| `suremembers/grant-membership` | Edit | non-destructive | `Inc\Access::grant()` |
| `suremembers/revoke-membership` | Edit | non-destructive | `Inc\Access::revoke()` |
| `suremembers/update-membership-expiration` | Edit | idempotent | `Routers\Users::update_membership_expiration()` |
| `suremembers/create-membership` | Edit | non-destructive | `wp_insert_post` (`wsm_access_group`) |
| `suremembers/delete-membership` | Delete | **destructive** | `Routers\Members::delete_membership()` |

### Pro (SureMembers premium)

Registered into Core's registry via the `suremembers_register_abilities` hook.

| Ability | Gate | Wraps |
|---|---|---|
| `suremembers/get-drip-schedule` | read | `Modules\Drip\Drip::get_drip_data()` |
| `suremembers/get-downloads-report` | read | `Access_Groups::by_download_id()` + downloads meta |
| `suremembers/get-login-restriction-stats` | read | `Settings::get_setting( SUREMEMBERS_LOGIN_RESTRICTIONS_SETTINGS )` |
| `suremembers/get-role-sync-status` | read | `Access_Groups::get_selected_user_roles()` |
| `suremembers/import-members-csv` | Edit | `Modules\Import_Users\Import_Users::import_users_data()` |
| `suremembers/bulk-grant-membership` | Edit | loops `Inc\Access::grant()` |

---

## Permissions & gating

Each ability's `permission_callback` checks, in order:

1. **Master toggle** — `suremembers_abilities_api` must be on, else nothing registers.
2. **Per-ability gate** — edit abilities require `suremembers_abilities_api_edit`;
   delete abilities require `suremembers_abilities_api_delete`.
3. **Capability** — the current user must have `manage_options`.

### Where settings live

All four toggles are stored in a **single option array**,
`SUREMEMBERS_ABILITIES_SETTINGS` (`suremembers_abilities_settings`), mirroring
the **Redirection Rules** settings pattern:

```php
get_option( 'suremembers_abilities_settings' ) === [
    'suremembers_abilities_api'        => bool,
    'suremembers_abilities_api_edit'   => bool,
    'suremembers_abilities_api_delete' => bool,
    'suremembers_mcp_server'           => bool,
];
```

`SureMembersCore\Inc\Services\Abilities\Abilities_Settings` is the single
accessor used by the settings UI, the WP-CLI command, and the ability
permission callbacks — so the UI, CLI, and runtime never drift.

---

## MCP server

When **Enable MCP Server** is on and the **MCP Adapter** plugin is active,
SureMembers registers a dedicated MCP server collecting all `suremembers/*`
abilities (Core + Pro):

- **Endpoint:** `/wp-json/suremembers/v1/mcp`
- **Auth:** WordPress Application Password (`WP_API_USERNAME` / `WP_API_PASSWORD`).
- The **AI Abilities** settings tab generates ready-to-paste client config for
  Claude Desktop, Claude Code, Cursor, VS Code (Copilot), Continue, and others.

---

## REST endpoints

| Endpoint | Method | Purpose |
|---|---|---|
| `/wp-json/wp-abilities/v1/abilities` | GET | List/inspect registered abilities (core WP Abilities API) |
| `/wp-json/suremembers/v1/mcp-settings` | GET / POST | Read/save the AI ability toggles + MCP adapter status |
| `/wp-json/suremembers/v1/mcp` | — | MCP server endpoint (only when MCP Server is enabled) |

All require `manage_options`.

---

## Architecture

```
suremembers-core/
├── inc/services/abilities/
│   ├── ability.php              # Abstract base (schema, validation, permission, REST helper)
│   ├── abilities-settings.php   # Abilities_Settings — single source of truth for toggles
│   ├── registry.php             # Registry (singleton) — registers abilities + WP Abilities hooks
│   └── handlers/                # One file per ability (Handlers\* sub-namespace)
├── inc/services/cli/
│   └── abilities-command.php    # `wp suremembers abilities` command
└── inc/modules/mcp/
    └── module.php               # MCP settings REST endpoint + MCP server registration

suremembers/ (Pro)
└── inc/abilities/
    ├── registry.php             # Hooks suremembers_register_abilities → registers Pro handlers
    └── handlers/                # Pro abilities, extend the Core Ability base class
```

Registration lifecycle:
- `rest_api_init` (pri 5) → Core `Registry::maybe_init()` registers built-ins, then
  fires `do_action( 'suremembers_register_abilities', $registry )`.
- `wp_abilities_api_categories_init` → registers the `suremembers` category.
- `wp_abilities_api_init` → registers each enabled ability with the WP Abilities API.

---

## Extending (add your own abilities)

Pro and third-party plugins add abilities through the Core registry:

```php
add_action( 'suremembers_register_abilities', function ( $registry ) {
    $registry->register( new \Your\Namespace\Handlers\My_Ability() );
} );
```

Each handler extends `SureMembersCore\Inc\Services\Abilities\Ability` and
implements `get_id()`, `get_name()`, `get_description()`, `get_category()`,
`get_parameters()`, and `execute()`. Set `protected string $gated =
'suremembers_abilities_api_edit';` (or `_delete`) to place an ability behind a
gate, and return MCP `get_annotations()` (`readOnlyHint`, `destructiveHint`,
`idempotentHint`) to describe its behavior.
