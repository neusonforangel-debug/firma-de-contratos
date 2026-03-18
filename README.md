# REDWM Firma Digital PRO (PHP + MySQL)

## Qué incluye
- Creación de cliente + contrato anual (12 meses)
- Envío de código por email (OTP) para firmar
- Firma dibujada en canvas (PNG) + evidencia (IP/UA/fecha UTC)
- Generación de PDF (MinimalPDF) con firma embebida
- Página de verificación (hash de evidencia)
- Panel admin: login, listado, búsqueda, reenviar código, descargar/verificar

## Instalación en cPanel
1. Sube la carpeta completa al `public_html/firma` (o donde quieras).
2. Crea la base de datos MySQL y usuario.
3. Ejecuta `database/schema.sql` en phpMyAdmin.
4. Edita `app/config.php`:
   - BASE_URL
   - DB_*
   - APP_KEY (muy larga y aleatoria)
   - MAIL_*
5. Crea el admin:
   - Abre `/install/create_admin.php?allow=1`
   - Crea el usuario
   - **Borra la carpeta `/install`** por seguridad.

## URLs
- Landing: /public/index.php
- Firma: /public/sign.php?token=...
- Verificación: /public/verify.php?token=...
- Admin: /admin/login.php

## Personalizar contrato
Edita: `app/contract/template.php`

> Nota legal: Si vas a usar cláusula de centrales de riesgo, valida el texto final con asesoría legal para tu caso.
