# Profile Trader Dashboard

A WordPress plugin that provides a frontend dashboard for traders to manage their listings in the "دليل التجار" (Traders Directory).

## Features

- **Full RTL Arabic Support**: Designed specifically for Arabic language with proper RTL layout
- **Modern Dashboard UI**: Clean, responsive design with teal & gold color scheme
- **Listings Management**: View, edit, and add new trader listings
- **Profile Management**: Update personal information and password
- **Media Uploads**: Logo and gallery image uploads using WordPress media library
- **Dynamic Forms**: Repeater fields for services/products and branches
- **Status Tracking**: Published, pending, and draft status indicators
- **Featured Listings**: Special badge for featured traders

## Installation

1. Upload the `profile-trader` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. A "Trader Dashboard" page will be created automatically

## Shortcodes

### `[trader_dashboard]`
Displays the full dashboard with sidebar navigation.

### `[trader_listings]`
Displays only the listings grid (standalone).

### `[trader_edit_form]`
Displays only the edit form (standalone).
- Optional parameter: `id` - The listing ID to edit

## Requirements

- WordPress 5.8+
- PHP 7.4+
- JetEngine or similar plugin for the `trader` custom post type

## Meta Fields Supported

The plugin works with the following meta fields from the trader post type:

| Field Name | Label | Type |
|------------|-------|------|
| `short_desc` | وصف قصير | Textarea |
| `website` | الموقع الالكتروني | URL |
| `email` | الايميل | Email |
| `phone` | رقم الهاتف | Tel |
| `whatsapp` | واتساب | Tel |
| `facebook_page` | صفحة الفيس | URL |
| `instagram_page` | صفحة الانستغرام | URL |
| `date_of_grant_of_record` | تاريخ منح السجل | Date |
| `map_location` | العنوان الفرع الرئيسي | Text |
| `commercial_register` | سجل تجاري | Text |
| `score` | درجة السجل | Radio |
| `company_type` | نوع الشركة | Radio |
| `services` | تصنيف المنتجات | Repeater |
| `bracnches` | الفروع | Repeater |
| `is_featured` | عضو مميز | Checkbox |
| `status_editing` | حالة التعديل | Radio |
| `gallary` | معرض الصور | Gallery |
| `logo` | لوجو | Media |

## File Structure

```
profile-trader/
├── profile-trader.php          # Main plugin file
├── README.md                   # This file
├── assets/
│   ├── css/
│   │   └── dashboard.css       # Dashboard styles
│   └── js/
│       └── dashboard.js        # Dashboard JavaScript
├── includes/
│   └── class-profile-handler.php  # Profile update handler
└── templates/
    ├── dashboard.php           # Main dashboard template
    ├── listings.php            # Standalone listings template
    ├── edit-form.php           # Standalone edit form template
    └── partials/
        ├── overview.php        # Dashboard overview partial
        ├── listings-list.php   # Listings grid partial
        ├── edit-form.php       # Edit form partial
        └── profile.php         # Profile settings partial
```

## Hooks & Filters

### Actions
- `pt_before_save_listing` - Before listing is saved
- `pt_after_save_listing` - After listing is saved

### Filters
- `pt_meta_fields` - Modify the meta fields configuration
- `pt_dashboard_tabs` - Add/remove dashboard tabs

## Security

- All form submissions are protected with WordPress nonces
- User ownership verification for listing edits
- Proper data sanitization and escaping
- Capability checks for admin-only fields

## Styling

The plugin uses CSS custom properties for easy theming:

```css
:root {
    --pt-primary: #0d7377;      /* Primary teal color */
    --pt-accent: #d4a853;       /* Gold accent color */
    --pt-bg: #f8fafc;           /* Background color */
    --pt-surface: #ffffff;      /* Card/surface color */
    --pt-text: #1e293b;         /* Primary text color */
}
```

## License

GPL v2 or later

## Support

For support, please open an issue on the plugin repository.

