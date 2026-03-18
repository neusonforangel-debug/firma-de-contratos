<?php
require_once __DIR__ . '/../config.php';

function contract_text(array $c, array $k): string {

    $price = number_format(25000, 0, ',', '.');

    return "CONTRATO DE PRESTACIÓN DE SERVICIOS (SOFTWARE COMO SERVICIO - SAAS)\n"
    . "Versión: " . CONTRACT_VERSION . "\n\n"

    . "Entre: REDWM - WESHMARK (\"EL PROVEEDOR\") y " . $c['company_name'] . " NIT " . $c['nit'] . " (\"EL CLIENTE\").\n\n"

    . "1. OBJETO\n"
    . "EL PROVEEDOR prestará al CLIENTE el acceso y uso del software de facturación electrónica, incluyendo funcionalidades, actualizaciones y soporte asociado.\n\n"

    . "2. LICENCIA DE USO\n"
    . "Se otorga al CLIENTE una licencia de uso no exclusiva, intransferible y limitada al tiempo de vigencia del servicio. Queda prohibida su reventa, copia o modificación sin autorización expresa.\n\n"

    . "3. VALOR Y FORMA DE PAGO\n"
    . "El servicio tiene un costo de COP $ {$price} mensuales, pagados por adelantado.\n"
    . "La falta de pago faculta al PROVEEDOR para suspender o cancelar el servicio de forma inmediata.\n\n"

    . "4. SOPORTE Y DISPONIBILIDAD\n"
    . "EL PROVEEDOR brindará soporte técnico mediante canales digitales. La disponibilidad del sistema podrá verse afectada por mantenimientos o causas externas.\n\n"

    . "5. ACTUALIZACIONES\n"
    . "El sistema podrá ser actualizado de forma periódica para mejoras funcionales, legales o de seguridad.\n\n"

    . "6. LIMITACIÓN DE RESPONSABILIDAD\n"
    . "En ningún caso EL PROVEEDOR será responsable por daños indirectos, lucro cesante o pérdida de información.\n"
    . "La responsabilidad total del PROVEEDOR, en caso de demostrarse, se limitará al valor pagado por el CLIENTE en el último mes del servicio.\n\n"

    . "7. NO DEVOLUCIONES\n"
    . "Los valores pagados por el CLIENTE no son reembolsables bajo ninguna circunstancia, incluyendo cancelación anticipada o no uso del servicio.\n\n"

    . "8. RETIRO ANTICIPADO\n"
    . "En caso de que el CLIENTE decida dar por terminado el servicio antes del periodo contratado, deberá pagar la totalidad del plan contratado pendiente.\n\n"

    . "9. REPORTE A CENTRALES DE RIESGO\n"
    . "El CLIENTE autoriza expresamente al PROVEEDOR a reportar, consultar y actualizar su información en centrales de riesgo en caso de mora o incumplimiento en los pagos.\n\n"

    . "10. TRATAMIENTO DE DATOS\n"
    . "EL CLIENTE autoriza el tratamiento de sus datos personales conforme a la Ley 1581 de 2012. EL PROVEEDOR implementará medidas de seguridad adecuadas.\n\n"

    . "11. TERMINACIÓN\n"
    . "Cualquiera de las partes podrá dar por terminado el contrato. En caso de mora, el PROVEEDOR podrá suspender el servicio sin previo aviso.\n\n"

    . "12. JURISDICCIÓN\n"
    . "El presente contrato se rige por las leyes de la República de Colombia. Cualquier controversia será resuelta en la jurisdicción competente en Colombia.\n\n"

    . "13. ACEPTACIÓN\n"
    . "La firma digital realizada en esta plataforma constituye aceptación expresa del presente contrato.\n";
}
?>