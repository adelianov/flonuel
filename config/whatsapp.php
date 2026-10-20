
<?php
/*
 * Nomor WhatsApp admin menggunakan format internasional.
 * Contoh Indonesia: 6281234567890
 * Jangan gunakan tanda +, spasi, atau tanda hubung.
 */
define('ADMIN_WHATSAPP', '6283109643743');

function buatLinkWhatsApp($pesan)
{
    return 'https://wa.me/' . ADMIN_WHATSAPP
        . '?text=' . urlencode($pesan);
}

