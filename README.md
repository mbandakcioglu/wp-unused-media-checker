# 🧹 Unused Media Checker (WordPress Plugin)

**Unused Media Checker** helps you identify and manage **unused media files** within your WordPress site’s Media Library.

> ⚠️ Note: The plugin is currently in an early stage of development.  
> Some features, such as the delete button, may not work properly on all sites.

---

## 🚀 Features

- 🔍 Scans all media files in the WordPress Media Library  
- 🗑️ Lists only **unused files** (based on content and metadata analysis)  
- ⚡ AJAX-based scanning for smoother experience  
- 🧱 Works with common page builders (Elementor, Gutenberg, ACF, WooCommerce)  
- 🧩 Fully built using WordPress core APIs (no direct database queries)

---

## 🧠 How It Works

The plugin checks each attachment against:
1. Post content (`post_content`)
2. Post metadata (`get_post_meta`)
3. Featured images (`_thumbnail_id`)

If the file is not found anywhere, it is marked as **unused**.

> This approach ensures compatibility with a wide range of sites,  
> but may need further optimization for large media libraries.

---

## ⚙️ Installation

1. Download or clone the repository:
   ```bash
   git clone https://github.com/mbandakcioglu/wp-unused-media-checker.git
