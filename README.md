# Admin & Producer Dashboard - Streaming Platform

## Overview
This project is a comprehensive web-based dashboard designed for administrators, producers, and users of a movie streaming and production platform. It features a modern, responsive UI with support for dark and light themes, facilitating content management, financial tracking, social interaction, and analytics.

## Features

### 1. Dashboard & Analytics
- **Overview Stats:** Visual cards displaying key metrics with split-row data.
- **Activity Logs:** Styled tables for tracking approvals and system activities.
- **Charts:** CSS-based grid layouts for visual reporting.

### 2. Content Management (Movies)
- **Movie Library:** Responsive grid view of available content with interactive hover overlays.
- **Search & Filter:** Advanced search panel with genre tags and expanding search bars.
- **Upload Interface:** Dedicated control panel for producers to upload new content.
- **Detailed Views:** Immersive hero banners, circular cast scrollers, and video player modals.

### 3. Administration & Monetization
- **Approval Workflow:** Action buttons for admins to approve or reject submitted content.
- **Monetization Control:** Toggle switches to set content as Free or Premium.
- **Pricing:** Dynamic input areas for setting premium content pricing.

### 4. Social & Community
- **Forum:** A full-featured discussion board with tab filtering, post creation, likes, and nested comments.
- **Chat Application:** A split-pane messaging interface with contact lists, search, and real-time message bubbles.
- **Notifications:** Interactive dropdown system for user alerts.

### 5. User & Financials
- **User Profile:** Management of avatars, role badges, password security, and "Watching Habits" progress bars.
- **Wallet:** Digital wallet interface featuring balance cards, withdrawal actions, and transaction history.

## UI/UX Design
- **Theming Engine:** Built-in Dark Mode (default) and Light Mode support using CSS variables (`:root` vs `body.light-mode`).
- **Visual Effects:** Extensive use of hover glows, scaling animations, and glassmorphism (`backdrop-filter`).
- **Responsive Layout:** Utilizes CSS Grid and Flexbox to ensure compatibility across different screen sizes.

## File Structure

The styling is centralized in `Dashboard.css`, which is organized into the following sections:
1.  **Global Variables:** Color palettes for theming.
2.  **Layout:** Navbar, Main Content, Footer.
3.  **Components:** Buttons, Cards, Modals, Tables.
4.  **Page-Specific Styles:**
    *   Movies Page (Grid, Controls)
    *   Detail Page (Hero, Cast, Player)
    *   Profile & Wallet
    *   Forum & Chat

## Usage

To switch themes dynamically via JavaScript:
```javascript
// Enable Light Mode
document.body.classList.add('light-mode');

// Revert to Dark Mode
document.body.classList.remove('light-mode');
```