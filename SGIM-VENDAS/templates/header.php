<?php
require_once 'config/database.php';
$current_page = $current_page ?? '';
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>SGIM Vendas - Enterprise Dashboard</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
                "tertiary-fixed-dim": "#7cd0ff", "inverse-on-surface": "#313030", "on-tertiary-container": "#004d6a",
                "error": "#ffb4ab", "on-surface": "#e5e2e1", "on-tertiary-fixed-variant": "#004c69",
                "on-secondary-fixed-variant": "#5a4300", "tertiary-container": "#3ac2ff", "on-surface-variant": "#d7c3ae",
                "surface-container-lowest": "#0e0e0e", "on-error": "#690005", "surface-bright": "#393939",
                "surface-container-high": "#2a2a2a", "on-secondary-fixed": "#251a00", "surface-container-low": "#1c1b1b",
                "tertiary": "#9bd9ff", "outline-variant": "#524534", "outline": "#9f8e7a", "inverse-surface": "#e5e2e1",
                "on-tertiary-fixed": "#001e2c", "primary-fixed-dim": "#ffb955", "surface-variant": "#353534",
                "tertiary-fixed": "#c4e7ff", "on-secondary": "#3f2e00", "on-background": "#e5e2e1",
                "on-primary-container": "#644000", "error-container": "#93000a", "on-error-container": "#ffdad6",
                "surface": "#131313", "primary-fixed": "#ffddb4", "surface-container-highest": "#353534",
                "on-tertiary": "#00344a", "on-secondary-container": "#423000", "secondary-fixed-dim": "#f2bf3a",
                "inverse-primary": "#835500", "secondary-fixed": "#ffdf99", "surface-tint": "#ffb955",
                "surface-container": "#201f1f", "on-primary-fixed": "#291800", "secondary-container": "#c29400",
                "on-primary-fixed-variant": "#633f00", "primary-container": "#f5a623", "surface-dim": "#131313",
                "background": "#131313", "primary": "#ffc880", "on-primary": "#452b00", "secondary": "#f2bf3a"
            },
            "borderRadius": { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
            "fontSize": {
                "body-md": ["16px", {"lineHeight": "1.6", "fontWeight": "400"}],
                "title-sm": ["18px", {"lineHeight": "1.5", "fontWeight": "600"}],
                "display-lg": ["48px", {"lineHeight": "1.1", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                "body-sm": ["14px", {"lineHeight": "1.5", "fontWeight": "400"}],
                "headline-md": ["24px", {"lineHeight": "1.3", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                "label-caps": ["12px", {"lineHeight": "1.0", "letterSpacing": "0.05em", "fontWeight": "700"}]
            }
          },
        },
      }
    </script>
    <style>
      .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24; }
      .glass-card { background: rgba(26, 26, 26, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
      .sidebar-item-active { background: linear-gradient(90deg, rgba(245, 166, 35, 0.1) 0%, rgba(245, 166, 35, 0) 100%); }
    </style>
</head>
<body class="bg-background text-on-surface font-body-md overflow-x-hidden">
