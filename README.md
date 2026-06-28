# Tyese aiSite

Tyese aiSite is a WordPress plugin that builds editable Elementor website drafts from natural-language instructions.

It is designed to work inside the existing Elementor ecosystem. It does not replace Elementor and it does not create a separate visual page builder. It uses Elementor, Tyese Addon for Elementor, and compatible installed widgets to create WordPress pages that remain editable in Elementor.

## Requirements

- WordPress 6.4+
- PHP 7.4+
- Elementor installed and active
- Tyese Addon for Elementor installed and active, or the older first-working Tyese widget plugin while migrating
- OpenAI API key

## What It Does

- Adds a **Tyese aiSite** admin dashboard page.
- Stores an OpenAI API key and model setting.
- Accepts a website prompt, optional reference URL, and brand context.
- Scans installed Elementor widgets so the AI planner knows what it can use.
- Generates a structured site blueprint.
- Converts the blueprint into draft WordPress pages with Elementor data.
- Keeps generated pages editable through Elementor.

## Builder Philosophy

Tyese aiSite uses a controlled blueprint pipeline:

1. User enters a website prompt.
2. OpenAI returns structured JSON that follows the Tyese aiSite schema.
3. The plugin sanitizes and normalizes the blueprint.
4. The Elementor builder converts the blueprint into editable Elementor sections and widgets.
5. Pages are created as drafts for review before publishing.

The model is instructed to prefer real Elementor and Tyese widgets. HTML widgets are reserved for rare fallback cases.

## Reference URL Safety

Reference URLs are used only for layout inspiration, section order, and general UX patterns. Tyese aiSite should not copy protected branding, logos, proprietary text, trademarks, unique images, or other IP.

## First Version Scope

This first version includes the plugin scaffold, dashboard, settings, OpenAI blueprint client, widget inventory, blueprint schema, and draft Elementor page creation.

Next planned upgrades:

- Follow-up edit commands for existing pages.
- Better section-level regeneration.
- Media library and licensed stock image integration.
- Icon selection.
- Contact form plugin integrations.
- WooCommerce, GiveWP, and events integrations.
- Build history and rollback.
- SEO and accessibility checks.
