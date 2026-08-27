<?php
// Fabricante automático de Certificados VIP para QZ Tray (Versión XAMPP Fix)

// 1. Rastreador del archivo de configuración de OpenSSL de XAMPP
$rutas = [
    "C:/xampp/php/extras/ssl/openssl.cnf",
    "C:/xampp/apache/conf/openssl.cnf",
    "C:/xampp/apache/bin/openssl.cnf"
];

$ruta_conf = "";
foreach ($rutas as $r) {
    if (file_exists($r)) {
        $ruta_conf = $r;
        break;
    }
}

if (empty($ruta_conf)) {
    die("<h3 style='color:red;'>Error: No se encontró el archivo openssl.cnf en XAMPP.</h3>");
}

// 2. Le pasamos la ruta exacta a la configuración
$configargs = array(
    "config" => $ruta_conf,
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
);

$dn = array(
    "organizationName" => "3M TECHNOLOGY",
    "commonName" => "localhost"
);

// 3. Generar las llaves forzando la configuración de XAMPP
$privkey = openssl_pkey_new($configargs);

if (!$privkey) {
    die("Error de OpenSSL: " . openssl_error_string());
}

$csr = openssl_csr_new($dn, $privkey, $configargs);
$x509 = openssl_csr_sign($csr, null, $privkey, 3650, $configargs);

openssl_x509_export($x509, $certout);
openssl_pkey_export($privkey, $pkeyout, null, $configargs);

// Guardar los archivos físicamente
file_put_contents('digital-certificate.txt', $certout);
file_put_contents('private-key.pem', $pkeyout);

echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
echo "<h2>¡Magia Completada! 🎩✨</h2>";
echo "<p style='color:green;'>Configuración OpenSSL detectada en: <b>$ruta_conf</b></p>";
echo "<p>Las llaves VIP de 3M TECHNOLOGY han sido fabricadas exitosamente en tu carpeta <b>api/</b>.</p>";
echo "<p>Ya puedes cerrar esta pestaña y volver a tu sistema.</p>";
echo "</div>";
?>