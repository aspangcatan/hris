# CSMC SPMS - Design & UI Documentation

This document outlines the design system, visual identity, and UI/UX patterns used in the **E-SPMS (Strategic Performance Management System)**.

---

## 3. UI / UX Design

### Design Philosophy
The E-SPMS interface follows a **"Modern Professional"** aesthetic. It prioritizes clarity, readability, and ease of use, leveraging a clean, light-themed environment with high-contrast distinct accents. The design aims to reduce cognitive load by using generous spacing, card-based groupings, and subtle drop shadows.

### Layout Structure
- **Sidebar Layout**: A fixed 280px sidebar on the left for primary navigation.
- **Top Navigation**: A sticky top navbar (72px height) for user profile management and secondary actions.
- **Card-Based Interface**: Content is grouped into `.card-modern` containers with rounded corners (16px) and soft shadows to distinguish content from the background.
- **Grid System**: Utilizes the Bootstrap 5 and Tailwind CSS grid systems for responsive alignment.
- **Spacing**: Uses a consistent spacing scale (Tailwind's 4px scale), typically handling padding/margins in multiples of 4 (e.g., `p-4`, `m-8`).

### Navigation Patterns
- **Active States**: Navigation items highlight with a light orange background (`#fff5f2`) and orange text to indicate the current active page.
- **Hover Effects**: subtle interactions, such as text color changes and slight transforms (translateX) on sidebar items, provide immediate feedback.
- **Dropdowns**: Profile and action menus use rounded, shadowed dropdowns with clear separation lines.

### Accessibility Considerations
- **Typography**: Uses **Inter**, a highly readable sans-serif font optimized for computer screens.
- **Contrast**: High contrast between text (`#1a1e23`) and background (`#f7f8f9`).
- **Visual Hierarchy**: Clear separation of headings, labels, and body text using font weights (Bold `700`, Medium `500`) and uppercase tracking for labels.
- **Icons**: Font Awesome icons accompany text labels to aid visual scanning.

---

## 4. Theme & Visual Identity

### Overall Theme
- **Light Theme**: The application uses a strictly light theme to maintain a clean, document-oriented look suitable for government/corporate performance reviews.
- **Glass/Modern touches**: Subtle transparency and blurred shadows are used instead of distinct heavy borders.

### Design Inspiration
- **Modern SaaS Dashboards**: Influence from modern web application dashboards (like Stripe or Vercel) is evident in the usage of rounded corners, subtle off-white backgrounds, and refined typography.

### Brand Tone and Mood
- **Professional**: Serious and trustworthy (Grays/Whites).
- **Energetic**: The Primary Orange adds a layer of urgency and modernity appropriate for performance tracking.
- **Clean**: Minimalist approach to forms and data presentation.

---

## 5. Color System

The color palette is concise, relying on a neutral grayscale with a single strong primary accent color.

### Color Usage Guidelines
- **Primary Orange** is used for Call-to-Action (CTA) buttons, active navigation states, and key highlights.
- **Grays** are used for text hierarchy (Darkest for headings, Medium for body, Lightest for borders).
- **White** is the primary container background.
- **Off-White** (`#f7f8f9`) is the global page background.

### Color Palette

| Color Name | Purpose | HEX Value | RGB Value |
| :--- | :--- | :--- | :--- |
| **Primary Orange** | CTAs, Active States, Brand Accents | `#f06a38` | `240, 106, 56` |
| **Orange Light** | Active Backgrounds, Hover States | `#fff5f2` | `255, 245, 242` |
| **Dark Text** | Main Headings, Body Text | `#1a1e23` | `26, 30, 35` |
| **Secondary Text** | Subtitles, Meta info | `#4b5563` | `75, 85, 99` |
| **Muted Text** | Placeholders, Icons | `#64748b` | `100, 116, 139` |
| **Page Background** | Global Body Background | `#f7f8f9` | `247, 248, 249` |
| **Card / Surface** | Component Backgrounds | `#ffffff` | `255, 255, 255` |
| **Border Light** | Dividers, Card Borders | `#edf1f5` | `237, 241, 245` |
| **Border Dark** | Input Borders, Button Outlines | `#e5e7eb` | `229, 231, 235` |

---

## 6. Typography

### Font Families
- **Primary Font**: [Inter](https://fonts.google.com/specimen/Inter), Sans-serif.
  - Used for 100% of the UI text.

### Font Sizes & Hierarchy
| Element | Size | Weight | Tracking (Letter Spacing) | Usage |
| :--- | :--- | :--- | :--- | :--- |
| **Main Headings** | `text-2xl` (1.5rem) | Black/Bold (900/700) | `tracking-tight` | Page Titles, Brand Name |
| **Section Titles** | `text-xl` (1.25rem) | Bold (700) | Normal | Modal Titles, Card Headers |
| **Body Text** | `text-sm` (0.875rem) | Normal (400) | Normal | General Content, Form Inputs |
| **Small Utility** | `text-xs` (0.75rem) | Bold (700) | `tracking-widest` | Buttons, Badges, Nav items |
| **Micro Labels** | `text-[10px]` | Black (900) | `tracking-widest` | Overlines, Section Dividers |

---

## 7. Components & UI Elements

### Buttons
- **Primary Button** (`.btn-orange`):
  - Background: `#f06a38`
  - Text: White, Bold, Uppercase (optional)
  - Radius: `8px`
  - Hover: Opacity 0.9
- **Outline Button** (`.btn-outline-modern`):
  - Background: White
  - Border: `#e5e7eb`
  - Text: Dark Gray
  - Radius: `8px`

### Cards (`.card-modern`)
- **Style**: White background, `rounded-2xl` (16px), 1px solid `#f1f1f1` border.
- **Shadow**: `shadow-sm` (subtle elevation).
- **Usage**: Used to wrap all major content sections (Forms, Data Tables, Charts).

### Forms
- **Inputs**:
  - Background: `#f9fafb` (Gray-50)
  - Border: `#e5e7eb` (Gray-200)
  - Radius: `rounded-2xl`
  - Padding: Generous (py-3, px-4) for touch friendliness.
  - Focus: Ring with Primary Orange (`focus:ring-orange-500`).

### Modals (`.modal-content`)
- **Shape**: `rounded-3xl` (Large rounded corners).
- **Header**: Often dark (`bg-gray-900`) with white text for high contrast in security/important modals.
- **Backdrop**: Standard dimmed background.

### Alerts / Notifications (SweetAlert2)
- **Theme**: Custom "Modern" theme.
- **Styling**: `rounded-2xl`, Padding `2rem`.
- **Confirm Button**: Matches Primary Orange button style.
- **Icons**: Standard SweetAlert animated icons.

### Consistency Rules
1.  **Rounding**: Use `rounded-xl` or `rounded-2xl` for containers; `rounded-lg` for smaller inner elements (buttons, inputs). Avoid sharp corners.
2.  **Shadows**: Use clear, diffuse shadows (`shadow-lg`, `shadow-xl`) only for floating elements (Dropdowns, Modals). Static elements use `shadow-sm`.
3.  **Uppercase**: Use Uppercase + Wide Tracking for "Micro Labels" (e.g., "MAIN MENU", "SYSTEM VERSION") to distinguish them from actionable text.

---

## 8. Responsiveness

### Desktop (>= 1024px)
- **Sidebar**: Visible and fixed on the left.
- **Content**: Margin-left adjusted (`280px`) to account for sidebar.
- **Navbar**: Full width of the content area.

### Tablet & Mobile (< 1024px)
- **Sidebar**: Hidden by default (`transform: translateX(-100%)`).
- **Toggle**: Hamburger menu icon (`.fa-bars`) appears in the top-left.
- **Drawer**: Sidebar slides in from the left when toggled (`.show` class).
- **Backdrop**: A click outside the sidebar closes it.
- **Content**: Full width (No margin).
- **Typography**: Sizes generally scale down slightly using Tailwind's responsive utilities (e.g., `text-xl md:text-2xl`).
