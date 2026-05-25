# Drupal MCP

Exposes a **Model Context Protocol (MCP) server** over HTTP so that an AI agent can
exercise full programmatic control over a Drupal 11 site: creating content types,
managing fields, taxonomies, roles, permissions, users, and nodes.

Every capability is implemented as a discrete, discoverable **MCP Tool**. Tools are
disabled by default and must be explicitly enabled by a site administrator.

---

## Requirements

| Item | Minimum |
|---|---|
| Drupal | 11.x |
| PHP | 8.3 |
| Core modules | node, taxonomy, user, field, field_ui, text, media |

---

## Installation

Place the module in `web/modules/custom/drupal_mcp/` (or install via Composer if
packaged), then enable it:

```bash
# Core MCP server only (no admin UI)
drush en drupal_mcp -y

# Core + admin UI (recommended for initial setup)
drush en drupal_mcp drupal_mcp_ui -y

drush cr
```

### Sub-modules

All sub-modules live in `drupal_mcp/modules/` and are auto-discovered by Drupal.
Enable only the ones you need.

| Sub-module | Requires | What it adds |
|---|---|---|
| `drupal_mcp_ui` | — | Admin UI: bearer-token form + tool toggle form |
| `drupal_mcp_link` | `drupal:link` | `link` field type in `field_create` |
| `drupal_mcp_telephone` | `drupal:telephone` | `telephone` field type in `field_create` |
| `drupal_mcp_daterange` | `drupal:datetime_range` | `daterange` field type in `field_create` |
| `drupal_mcp_paragraphs` | `paragraphs`, `entity_reference_revisions` | `entity_reference_revisions` field type + `paragraph_type_*` tools |
| `drupal_mcp_field_group` | `field_group` | `field_group_*` tools for display grouping |
| `drupal_mcp_layout_paragraphs` | `layout_paragraphs`, `drupal_mcp_paragraphs` | `layout_list` + `paragraph_type_configure_layout` + `paragraph_field_configure_layout_display` tools |

**`drupal_mcp_ui`** provides two admin forms:

- **`/admin/config/services/mcp`** — set the bearer token
- **`/admin/config/services/mcp/tools`** — enable or disable individual tools

It is entirely optional. Headless or CI/CD installs can configure everything through
Drush or config import (see below).

---

## Configuration

### Step 1 — Set a bearer token

The MCP endpoint requires a bearer token on every request. Choose a strong random
secret and never commit it to version control.

**Generate a token:**
```bash
openssl rand -hex 32
```

**With `drupal_mcp_ui` enabled:**
1. Go to **Administration → Configuration → Web services → MCP server settings**
   (`/admin/config/services/mcp`)
2. Paste the token and save.

**Without the UI — Drush:**
```bash
drush config-set drupal_mcp.settings bearer_token "your-secret-token"
```

**Without the UI — config YAML (for deployment pipelines):**

Create or update `config/drupal_mcp.settings.yml`:
```yaml
bearer_token: 'your-secret-token'
enabled_tools: []
```
Then import:
```bash
drush cim --partial --source=/path/to/config/directory
```

**Without the UI — settings.php override (environment-based):**
```php
// web/sites/default/settings.local.php
$config['drupal_mcp.settings']['bearer_token'] = getenv('MCP_BEARER_TOKEN');
```

---

### Step 2 — Enable tools

All tools are **disabled by default**. You must explicitly enable the ones you want
to expose.

**With `drupal_mcp_ui` enabled:**
1. Go to **Administration → Configuration → Web services → MCP tools**
   (`/admin/config/services/mcp/tools`)
2. Check the tools you want to enable and save.

**Without the UI — Drush:**
```bash
drush config-set drupal_mcp.settings enabled_tools \
  '["content_type_create","content_type_list","node_create","node_list"]' \
  --input-format=json
```

**Without the UI — config YAML:**
```yaml
# config/drupal_mcp.settings.yml
bearer_token: 'your-secret-token'
enabled_tools:
  - content_type_create
  - content_type_update
  - content_type_delete
  - content_type_list
  - node_create
  - node_list
```

---

## MCP endpoint

```
POST /mcp/v1
Authorization: Bearer <your-token>
Content-Type: application/json
```

The endpoint speaks **JSON-RPC 2.0** and supports two methods:

### `tools/list` — discover enabled tools

```bash
curl -X POST https://your-site.example/mcp/v1 \
  -H "Authorization: Bearer your-secret-token" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":"1","method":"tools/list","params":{}}'
```

Response:
```json
{
  "jsonrpc": "2.0",
  "id": "1",
  "result": {
    "tools": [
      {
        "name": "content_type_create",
        "description": "Creates a new Drupal content type.",
        "inputSchema": { "type": "object", "properties": { ... } }
      }
    ]
  }
}
```

### `tools/call` — execute a tool

```bash
curl -X POST https://your-site.example/mcp/v1 \
  -H "Authorization: Bearer your-secret-token" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": "2",
    "method": "tools/call",
    "params": {
      "name": "content_type_create",
      "arguments": {
        "machine_name": "blog",
        "label": "Blog post",
        "description": "A blog post content type."
      }
    }
  }'
```

Response on success:
```json
{
  "jsonrpc": "2.0",
  "id": "2",
  "result": {
    "machine_name": "blog",
    "label": "Blog post"
  }
}
```

Response on error:
```json
{
  "jsonrpc": "2.0",
  "id": "2",
  "error": {
    "code": -32000,
    "message": "Tool content_type_create is disabled."
  }
}
```

### JSON-RPC error codes

| Code | Meaning |
|---|---|
| `-32700` | Parse error — invalid JSON |
| `-32600` | Invalid request — malformed envelope |
| `-32601` | Method not found |
| `-32602` | Invalid params — missing required argument |
| `-32603` | Internal error — unexpected server error |
| `-32000` | Tool disabled |
| `-32001` | Tool not found |

---

## Tool reference

### content_type

| Tool ID | Description | Required arguments |
|---|---|---|
| `content_type_create` | Create a content type | `machine_name`, `label` |
| `content_type_update` | Update label or description | `machine_name` |
| `content_type_delete` | Delete a content type | `machine_name` |
| `content_type_list` | List all content types | — |

### field

| Tool ID | Description | Required arguments |
|---|---|---|
| `field_create` | Add a field to a bundle | `entity_type`, `bundle`, `field_name`, `field_type`, `label` |
| `field_update` | Update field label or required flag | `entity_type`, `bundle`, `field_name` |
| `field_delete` | Remove a field from a bundle | `entity_type`, `bundle`, `field_name` |
| `field_list` | List fields on a bundle | `entity_type`, `bundle` |
| `paragraph_field_configure_layout_display` | Switch a paragraphs field's widget and formatter to `layout_paragraphs` | `entity_type`, `bundle`, `field_name` |

> **`paragraph_field_configure_layout_display`** requires the `drupal_mcp_layout_paragraphs` sub-module and only works on `entity_reference_revisions` fields.
> Optional parameters: `nesting_depth` (integer 0–5, default `0`), `require_layouts` (boolean, default `false`), `form_mode` (string, default `"default"`), `view_mode` (string, default `"default"`).

> **Supported field types** depend on which sub-modules are active. The base module
> provides: `boolean`, `datetime`, `decimal`, `email`, `entity_reference`, `file`,
> `float`, `image`, `integer`, `list_string`, `string`, `text_long`.
> Enable `drupal_mcp_link`, `drupal_mcp_telephone`, or `drupal_mcp_daterange` to
> unlock additional types. The `field_type` enum returned by `tools/list` always
> reflects what is currently active — no manual sync needed.

> **Media convention:** `field_type: image` and `field_type: file` are rejected when
> `entity_type: node`. Use `field_type: entity_reference` pointing to a `media`
> bundle (image, document, video, remote_video, audio) instead.

### field_group

Requires the `drupal_mcp_field_group` sub-module (and the contrib `field_group` module).

| Tool ID | Description | Required arguments |
|---|---|---|
| `field_group_create` | Create a group on a form or view display | `entity_type`, `bundle`, `display_mode`, `group_name`, `label` |
| `field_group_delete` | Remove a group from a display | `entity_type`, `bundle`, `display_mode`, `group_name` |
| `field_group_list` | List all groups on a display | `entity_type`, `bundle`, `display_mode` |

`display_mode` is either `form` or `view`. `group_name` must start with `group_`.

### layout

Requires the `drupal_mcp_layout_paragraphs` sub-module (and contrib `layout_paragraphs` + `drupal_mcp_paragraphs`).

| Tool ID | Description | Required arguments |
|---|---|---|
| `layout_list` | List all registered Drupal layout plugins with their regions | — |
| `paragraph_type_configure_layout` | Enable the `layout_paragraphs` behavior on a paragraph type and set available layouts | `machine_name`, `available_layouts` |
| `paragraph_field_configure_layout_display` | Switch an `entity_reference_revisions` field's widget (form) and formatter (view) to `layout_paragraphs` | `entity_type`, `bundle`, `field_name` |

`layout_list` returns:
```json
{
  "items": [{ "id": "layout_onecol", "label": "One column", "regions": [{"name": "content", "label": "Content"}] }],
  "count": 1
}
```
Pass `include_regions: false` for a compact list (omits the `regions` array).

`paragraph_type_configure_layout` accepts:
- `machine_name` — existing paragraph type (must already exist via `paragraph_type_create`)
- `available_layouts` — array of layout IDs from `layout_list` (e.g. `["layout_onecol", "layout_twocol"]`)
- `enabled` — optional boolean, default `true`

`paragraph_field_configure_layout_display` accepts:
- `entity_type`, `bundle`, `field_name` — the `entity_reference_revisions` field to rewire
- `nesting_depth` — integer 0–5, default `0` (0 = flat, no nested layouts)
- `require_layouts` — boolean, default `false`
- `form_mode` / `view_mode` — display mode name, default `"default"`

#### Typical layout paragraphs workflow

```
# 1. Discover available layout IDs
layout_list

# 2. Create a paragraph type that will act as a layout section
paragraph_type_create  { machine_name: "layout_section", label: "Layout section" }

# 3. Mark it as a layout container with the desired layouts
paragraph_type_configure_layout  { machine_name: "layout_section", available_layouts: ["layout_onecol", "layout_twocol"] }

# 4. Add a paragraphs field to a content type
field_create  { entity_type: "node", bundle: "article", field_name: "field_sections",
               field_type: "entity_reference_revisions", label: "Sections" }

# 5. Switch that field's widget/formatter to layout_paragraphs
paragraph_field_configure_layout_display  { entity_type: "node", bundle: "article",
                                            field_name: "field_sections", nesting_depth: 1 }
```

### paragraph_type

Requires the `drupal_mcp_paragraphs` sub-module (and the contrib `paragraphs` + `entity_reference_revisions` modules).

| Tool ID | Description | Required arguments |
|---|---|---|
| `paragraph_type_create` | Create a paragraph type | `machine_name`, `label` |
| `paragraph_type_update` | Update label or description | `machine_name` |
| `paragraph_type_delete` | Delete a paragraph type | `machine_name` |
| `paragraph_type_list` | List all paragraph types | — |

Use `field_type: entity_reference_revisions` with `field_create` to attach paragraphs to a content type.
To enable layout-based editing, see the [**layout**](#layout) section above.

### media_type

| Tool ID | Description | Required arguments |
|---|---|---|
| `media_type_create` | Create a media type | `machine_name`, `label`, `source` |
| `media_type_update` | Update label or description | `machine_name` |
| `media_type_delete` | Delete a custom media type | `machine_name` |
| `media_type_list` | List all media types | — |

Valid `source` values: `image`, `file`, `oembed:video`, `audio_file`, `video_file`.

### taxonomy

| Tool ID | Description | Required arguments |
|---|---|---|
| `vocabulary_create` | Create a vocabulary | `machine_name`, `label` |
| `vocabulary_update` | Update vocabulary label/description | `machine_name` |
| `vocabulary_delete` | Delete a vocabulary | `machine_name` |
| `vocabulary_list` | List all vocabularies | — |
| `term_create` | Create a taxonomy term | `vocabulary`, `name` |
| `term_update` | Update a term | `tid` |
| `term_delete` | Delete a term | `tid` |
| `term_list` | List terms in a vocabulary | `vocabulary` |

### role

| Tool ID | Description | Required arguments |
|---|---|---|
| `role_create` | Create a user role | `machine_name`, `label` |
| `role_update` | Update role label or weight | `machine_name` |
| `role_delete` | Delete a custom role | `machine_name` |
| `role_list` | List all roles | — |

> System roles `anonymous`, `authenticated`, and `administrator` cannot be deleted.

### permission

| Tool ID | Description | Required arguments |
|---|---|---|
| `permission_grant` | Grant a permission to a role | `role`, `permission` |
| `permission_revoke` | Revoke a permission from a role | `role`, `permission` |
| `permission_list` | List all permissions (or by role) | — |

### user

| Tool ID | Description | Required arguments |
|---|---|---|
| `user_create` | Create a user account | `name`, `mail`, `password` |
| `user_update` | Update a user account | `uid` |
| `user_delete` | Delete a user account | `uid` |
| `user_assign_role` | Grant or revoke a role on a user | `uid`, `role`, `action` |

> User with `uid: 1` (superadmin) cannot be deleted.

### node

| Tool ID | Description | Required arguments |
|---|---|---|
| `node_create` | Create a node | `type`, `title` |
| `node_update` | Update a node | `nid` |
| `node_delete` | Delete a node | `nid` |
| `node_list` | List nodes with optional filters | — |

---

## Security

- **Token strength:** use at least 32 random bytes (`openssl rand -hex 32`).
- **Never commit the token** to version control. Use environment variables or Drupal's
  config override system in `settings.php`.
- **Principle of least privilege:** all tools are disabled by default. Enable only what
  the AI agent actually needs.
- **Access control:** the `administer mcp tools` permission is marked `restrict access`.
  Assign it only to trusted administrator roles.
- **No session auth:** the endpoint does not accept Drupal session cookies. Only the
  pre-shared bearer token is accepted.
- **Protected entities:** the module refuses to delete uid=1, system roles
  (`anonymous`, `authenticated`, `administrator`), or core media types
  (`image`, `video`, `document`, `audio`, `remote_video`).
- **Error safety:** exceptions are logged server-side but never exposed in MCP
  responses. Clients receive a generic error message only.

---

## Connecting to an AI agent

`drupal_mcp` implements the **MCP HTTP transport** (JSON-RPC over HTTP POST). Any
MCP-compatible client — desktop apps, IDE extensions, CLI tools, or custom agents —
can connect to it. The three values every client needs:

| Setting | Value |
|---|---|
| Transport | HTTP |
| URL | `https://your-site.example/mcp/v1` |
| Auth header | `Authorization: Bearer <your-token>` |

> **Local development:** the AI client must be able to reach the Drupal site. With
> DDEV, use `ddev share` to get a public tunnel URL instead of `localhost`.

---

### Generic JSON config (most desktop clients)

Most MCP-compatible clients (Claude Desktop, Cursor, Windsurf, Zed, Continue, etc.)
share a common JSON config format. Locate your client's MCP server config file and
add:

```json
{
  "mcpServers": {
    "drupal-mcp": {
      "url": "https://your-site.example/mcp/v1",
      "headers": {
        "Authorization": "Bearer your-secret-token"
      }
    }
  }
}
```

Some clients use `type: "http"` or `transport: "http"` alongside the `url` key —
check your client's documentation if the above does not work.

**Common config file locations:**

| Client | Config file |
|---|---|
| Claude Desktop (macOS) | `~/Library/Application Support/Claude/claude_desktop_config.json` |
| Claude Desktop (Windows) | `%APPDATA%\Claude\claude_desktop_config.json` |
| Cursor | `.cursor/mcp.json` in the project root, or `~/.cursor/mcp.json` globally |
| Windsurf | `~/.codeium/windsurf/mcp_config.json` |
| Zed | `~/.config/zed/settings.json` under `"context_servers"` |
| Continue | `.continue/config.json` under `"mcpServers"` |

After saving, restart the client. The enabled tools should appear in the tool picker
or agent context.

---

### CLI-first clients and custom agents

If you are building a custom agent or using an SDK, point your MCP client
implementation at the endpoint and pass the bearer token as an HTTP header on every
request. The server speaks standard JSON-RPC 2.0 — see the **MCP endpoint** section
above for the exact wire format.

Example using the MCP TypeScript SDK:
```typescript
import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import { StreamableHTTPClientTransport } from "@modelcontextprotocol/sdk/client/streamableHttp.js";

const transport = new StreamableHTTPClientTransport(
  new URL("https://your-site.example/mcp/v1"),
  { headers: { Authorization: "Bearer your-secret-token" } },
);

const client = new Client({ name: "my-agent", version: "1.0.0" });
await client.connect(transport);

const tools = await client.listTools();
```

---

### Claude (Code + Desktop)

#### Claude Code (CLI)

One-liner to register the server in the current project:

```bash
claude mcp add --transport http drupal-mcp https://your-site.example/mcp/v1 \
  --header "Authorization: Bearer your-secret-token"
```

Or add it to `.claude/settings.json` manually (project-scoped) or
`~/.claude/settings.json` (user-scoped):

```json
{
  "mcpServers": {
    "drupal-mcp": {
      "type": "http",
      "url": "https://your-site.example/mcp/v1",
      "headers": {
        "Authorization": "Bearer your-secret-token"
      }
    }
  }
}
```

Verify with:
```bash
claude mcp list
claude mcp get drupal-mcp
```

#### Claude Desktop

Edit `claude_desktop_config.json`:
- **macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`
- **Windows:** `%APPDATA%\Claude\claude_desktop_config.json`

```json
{
  "mcpServers": {
    "drupal-mcp": {
      "url": "https://your-site.example/mcp/v1",
      "headers": {
        "Authorization": "Bearer your-secret-token"
      }
    }
  }
}
```

Restart Claude Desktop. The enabled tools appear automatically in the tool picker.

---

### Verifying the connection

Once connected, ask the AI to list the available tools or run a quick sanity check:

> *"What Drupal tools do you have access to? List the content types on the site."*

This triggers `tools/list` followed by `content_type_list`. If both succeed the
connection is working correctly.

### Pre-flight checklist

- [ ] Bearer token set in `drupal_mcp.settings`
- [ ] At least one tool enabled
- [ ] Site reachable over HTTPS from the client machine
- [ ] Local dev: using a tunnel URL, not `localhost`
- [ ] Client restarted after config change

---

## Extending: adding custom MCP tools

Any Drupal module can add new MCP tools without patching `drupal_mcp`.

**1. Implement `McpToolInterface`:**
```php
<?php

declare(strict_types=1);

namespace Drupal\my_module\Plugin\McpTool;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\drupal_mcp\Attribute\McpTool;
use Drupal\drupal_mcp\Plugin\McpTool\McpToolInterface;
use Drupal\drupal_mcp\ValueObject\McpResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[McpTool(
  id: 'my_custom_action',
  label: 'My custom action',
  description: 'Does something specific to my module.',
  category: 'my_module',
)]
final class MyCustomActionTool implements McpToolInterface {

  public function __construct(
    // inject only real service dependencies
  ) {}

  public static function create(
    ContainerInterface $container,
    array $configuration,
    mixed $plugin_id,
    mixed $plugin_definition,
  ): static {
    return new static();
  }

  public function getInputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'my_param' => ['type' => 'string', 'description' => 'Example parameter.'],
      ],
      'required' => ['my_param'],
    ];
  }

  public function execute(array $input): McpResponse {
    try {
      $result = ['done' => TRUE, 'my_param' => $input['my_param']];
      return McpResponse::success(NULL, $result);
    }
    catch (\Throwable $e) {
      return McpResponse::error(NULL, -32603, 'Internal error.');
    }
  }

}
```

**2. Enable the tool:**
```bash
# After clearing caches, the tool appears in tools/list when enabled:
drush config-set drupal_mcp.settings enabled_tools \
  '["my_custom_action"]' --input-format=json
```

The plugin manager discovers tools automatically via PHP attribute scanning — no
service registration is required in the custom module.

---

## Extending: adding a custom field type

To make `field_create` accept a new field type (e.g. a contrib field type your
module depends on), implement `FieldTypeProviderInterface` and tag the service —
no changes to `CreateFieldTool` needed.

**1. Implement the interface:**
```php
<?php
// my_module/src/FieldType/MyFieldTypeProvider.php

declare(strict_types=1);

namespace Drupal\my_module\FieldType;

use Drupal\drupal_mcp\FieldType\FieldTypeProviderInterface;

final class MyFieldTypeProvider implements FieldTypeProviderInterface {

  public function getSupportedTypes(): array {
    return ['my_custom_type'];
  }

}
```

**2. Register and tag the service:**
```yaml
# my_module/my_module.services.yml
services:
  my_module.field_type_provider:
    class: Drupal\my_module\FieldType\MyFieldTypeProvider
    tags:
      - { name: drupal_mcp.field_type_provider }
```

After `drush cr`, `my_custom_type` appears automatically in the `field_type` enum
that `tools/list` exposes for `field_create`. No other files need to change.
