// resources/js/Validation/fieldRegistry.js

export const fieldRegistry = {
    firstName: {
        type: "letters",
        required: true,
        min: 2,
        max: 40,
        titleCase: true,
        preventSpam: true,
        message: "Solo se permiten letras.",
        spamMessage: "Nombre no válido.",
    },

    lastName: {
        type: "letters",
        required: true,
        min: 2,
        max: 40,
        titleCase: true,
        preventSpam: true,
        message: "Solo se permiten letras.",
        spamMessage: "Apellido no válido.",
    },

    position: {
        type: "letters",
        required: true,
        min: 2,
        max: 50,
        titleCase: true,
        preventSpam: true,
        message: "Solo se permiten letras.",
        spamMessage: "Puesto no válido.",
    },

    email: {
        type: "email",
        required: true,
        min: 6,
        max: 80,
        message: "Correo inválido.",
        spamMessage: "Correo no válido.",
    },

    phone: {
        type: "numeric",
        required: true,
        min: 10,
        max: 10,
        message: "Debe contener 10 dígitos.",
    },

    street: {
        type: "address",
        required: true,
        min: 2,
        max: 80,
        preventSpam: true,
        spamLevel: "soft",
        titleCase: true,
        message: "Calle inválida.",
        spamMessage: "Calle no válida.",
    },

    externalNumber: {
        type: "alphanumeric",
        required: true,
        min: 1,
        max: 20,
        preventSpam: true,
        uppercase: true,
        message: "Número exterior inválido.",
    },

    internalNumber: {
        type: "alphanumeric",
        required: false,
        max: 20,
        preventSpam: true,
        uppercase: false,
        message: "Número interior inválido.",
    },

    postalCode: {
        type: "numeric",
        required: true,
        min: 5,
        max: 5,
        message: "El código postal debe tener exactamente 5 números.",
    },

    neighborhood: {
        type: "address",
        required: true,
        min: 2,
        max: 80,
        preventSpam: true,
        spamLevel: "soft",
        titleCase: true,
        message: "Colonia inválida.",
        spamMessage: "Colonia no válida.",
    },

    municipality: {
        type: "letters",
        required: true,
        min: 2,
        max: 80,
        titleCase: true,
        preventSpam: true,
        message: "Municipio inválido.",
    },

    addressState: {
        type: "letters",
        required: true,
        min: 2,
        max: 80,
        titleCase: true,
        preventSpam: true,
        message: "Estado inválido.",
    },

    mapsUrl: {
        type: "text",
        required: false,
        max: 255,
        message: "URL inválida.",
    },

    startDate: {
        type: "date",
        required: true,
        message: "Fecha no válida.",
    },

    department: {
        type: "text",
        required: true,
        min: 2,
        max: 50,
        titleCase: true,
        preventSpam: true,
        message: "Departamento inválido.",
        spamMessage: "Departamento no válido.",
    },

    employmentStatus: {
        type: "text",
        required: true,
        min: 2,
        max: 20,
        titleCase: true,
        message: "Estado inválido.",
    },

    bank: {
        type: "letters",
        required: true,
        min: 2,
        max: 50,
        titleCase: true,
        preventSpam: true,
        message: "Solo se permiten letras.",
        spamMessage: "Banco no válido.",
    },

    accountNumber: {
        type: "numeric",
        required: true,
        min: 10,
        max: 18,
        message: "Cuenta bancaria inválida.",
    },

    educationLevel: {
        type: "letters",
        required: true,
        min: 2,
        max: 40,
        titleCase: true,
        preventSpam: true,
        message: "Solo se permiten letras.",
        spamMessage: "Grado académico no válido.",
    },

    specialty: {
        type: "text",
        required: true,
        min: 2,
        max: 50,
        titleCase: true,
        preventSpam: true,
        spamLevel: "soft",
        message: "Especialidad inválida.",
        spamMessage: "Especialidad no válida.",
    },

    contractType: {
        type: "text",
        required: true,
        min: 2,
        max: 50,
        titleCase: true,
        preventSpam: true,
        message: "Tipo de contrato inválido.",
        spamMessage: "Tipo de contrato no válido.",
    },

    seniority: {
        type: "text",
        required: false,
        max: 50,
        readonly: true,
        message: "Antigüedad inválida.",
    },

    nss: {
        type: "numeric",
        required: false,
        min: 11,
        max: 11,
        message: "NSS inválido.",
    },

    rfc: {
        type: "rfc",
        required: true,
        min: 12,
        max: 13,
        uppercase: true,
        message: "RFC inválido.",
    },

    producto: {
        type: "text",
        required: true,
        min: 2,
        max: 80,
        titleCase: true,
        preventSpam: true,
        message: "Nombre de producto inválido.",
        spamMessage: "Nombre de producto no válido.",
    },

    precio: {
        type: "decimal",
        required: true,
        min: 1,
        max: 10,
        message: "Precio inválido.",
    },

    codigoProducto: {
        type: "alphanumeric",
        required: true,
        min: 2,
        max: 30,
        uppercase: true,
        preventSpam: true,
        message: "Código inválido.",
        spamMessage: "Código de producto no válido.",
    },

    type: {
        type: "text",
        required: true,
        min: 2,
        max: 20,
        message: "Tipo de movimiento inválido.",
    },

    reason: {
        type: "text",
        required: true,
        min: 2,
        max: 30,
        message: "Motivo inválido.",
    },

    quantity: {
        type: "numeric",
        required: true,
        min: 1,
        max: 5,
        message: "Cantidad inválida.",
    },

    notes: {
        type: "text",
        required: false,
        max: 255,
        preventSpam: true,
        spamLevel: "soft",
        message: "Notas inválidas.",
        spamMessage: "Notas no válidas.",
    },
};
