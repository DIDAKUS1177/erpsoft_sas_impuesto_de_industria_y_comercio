<?php
/**
 * Microservicio Firmas Digitales — Configuración
 *
 * Edita este archivo antes de activar el módulo.
 * NO subas este archivo con credenciales reales a repositorios públicos.
 */
return [

    // ── Correo SMTP ─────────────────────────────────────────────────────────
    // Cuenta Gmail con "Contraseña de aplicación" habilitada (no la contraseña normal).
    // Para crear una contraseña de app: Google Account → Seguridad → Contraseñas de aplicación
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_user' => 'erpsoftsasmail@gmail.com',       // <-- CAMBIAR
    'smtp_password' => 'jbsn jbvw ysuj hibj',      // <-- CAMBIAR (contraseña de app, 16 chars)

    // ── Remitente que verá el usuario en su bandeja ──────────────────────────
    'from_name' => 'Alcaldía de paipa', // <-- CAMBIAR

    // ── Asunto del correo OTP ────────────────────────────────────────────────
    'otp_subject' => 'Código de verificación para firma digital',

];
