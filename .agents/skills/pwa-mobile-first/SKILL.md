---
name: pwa-mobile-first
description: Mobile-first and PWA development guidelines for tables, forms, and headers. Use this skill when modifying templates, stylesheets, scripts, or layout structures for responsive styling.
metadata:
  author: Neksa-Core
  version: "1.0.0"
---

# PWA & Mobile-First Development Guidelines

This skill defines the solid design systems and conventions to implement PWA features and responsive, mobile-first pages for the Neksa ERP.

## 1. Table to Card Responsive Conversion
All tabular records must be accessible and touch-friendly on mobile screens (viewport < 768px).
- **Wrapping HTML**: Ensure every listing table is wrapped in a `<div class="table-wrap">`.
- **Dynamic Headers**: The global layout JavaScript automatically matches `thead th` headers and applies a `data-label` attribute to all `tbody tr td` cells.
- **Do not duplicate loops**: Do not write separate loops/views for mobile cards and desktop tables. The CSS handles the reflow of `.table-wrap table` elements into cards on mobile.

## 2. Header and Footer Actions (Responsive Topbar/Footer Actions)
To keep the top navigation header clean and thumb-friendly on smaller screens:
- **Action yield**: Place page-specific buttons (like "Novo", "Exportar", "Salvar", "Filtrar") inside `@section('topbar-actions')`.
- **Layout rendering**:
  - **Desktop**: The buttons are rendered in `.topbar-actions` at the top right.
  - **Mobile**: The top `.topbar-actions` is hidden. Instead, the buttons are rendered at the bottom of the viewport in a fixed `.bottom-actions-bar`.
  - **Bottom Navigation**: If a page has action buttons, the standard `.bottom-nav` is automatically hidden on mobile to avoid overcrowding.

## 3. Form Usability and Tap Targets
- **Prevent Auto-Zoom on iOS**: Ensure all inputs, select dropdowns, and textareas have a minimum font-size of `16px` under mobile media queries to prevent browsers from automatically zooming into the layout.
- **Minimum Tap Area**: Interactive targets (buttons, links) must have a height/width of at least `44px` (and `48px` inside the `.bottom-actions-bar`) to be easily tapable.
