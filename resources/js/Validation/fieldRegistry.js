// resources/js/Validation/fieldRegistry.js

export const fieldRegistry = {
    nombre: {
        type: "letters",
        required: true,
        min: 2,
        max: 40,
        titleCase: true,
        preventSpam: true,
        message: "Solo se permiten letras.",
        spamMessage: "Nombre no válido.",
    },

    apellido: {
        type: "letters",
        required: true,
        min: 2,
        max: 40,
        titleCase: true,
        preventSpam: true,
        message: "Solo se permiten letras.",
        spamMessage: "Apellido no válido.",
    },

    puesto: {
        type: "letters",
        required: true,
        min: 2,
        max: 50,
        titleCase: true,
        preventSpam: true,
        message: "Solo se permiten letras.",
        spamMessage: "Puesto no válido.",
    },

    correo: {
        type: "email",
        required: true,
        min: 6,
        max: 80,
        message: "Correo inválido.",
        spamMessage: "Correo no válido.",
    },

    telefono: {
        type: "numeric",
        required: true,
        min: 10,
        max: 10,
        message: "Debe contener 10 dígitos.",
    },

    domicilio: {
        type: "address",
        required: true,
        min: 8,
        max: 120,
        titleCase: true,
        preventSpam: true,
        message: "Domicilio inválido.",
        spamMessage: "Domicilio no válido.",
    },

    fechaInicio: {
        type: "date",
        required: true,
        message: "Fecha no válida.",
    },

    departamento: {
        type: "text",
        required: true,
        min: 2,
        max: 50,
        titleCase: true,
        preventSpam: true,
        message: "Departamento inválido.",
        spamMessage: "Departamento no válido.",
    },

    estado: {
        type: "text",
        required: true,
        min: 2,
        max: 20,
        titleCase: true,
        message: "Estado inválido.",
    },

    banco: {
        type: "letters",
        required: true,
        min: 2,
        max: 50,
        titleCase: true,
        preventSpam: true,
        message: "Solo se permiten letras.",
        spamMessage: "Banco no válido.",
    },

    cuenta: {
        type: "numeric",
        required: true,
        min: 10,
        max: 18,
        message: "Cuenta bancaria inválida.",
    },

    grado: {
        type: "letters",
        required: true,
        min: 2,
        max: 40,
        titleCase: true,
        preventSpam: true,
        message: "Solo se permiten letras.",
        spamMessage: "Grado académico no válido.",
    },

    especialidad: {
        type: "letters",
        required: true,
        min: 2,
        max: 50,
        titleCase: true,
        preventSpam: true,
        message: "Solo se permiten letras.",
        spamMessage: "Especialidad no válida.",
    },

    tipoContrato: {
        type: "text",
        required: true,
        min: 2,
        max: 50,
        titleCase: true,
        preventSpam: true,
        message: "Tipo de contrato inválido.",
        spamMessage: "Tipo de contrato no válido.",
    },

    antiguedad: {
        type: "text",
        required: false,
        max: 50,
        readonly: true,
        message: "Antigüedad inválida.",
    },

    nss: {
        type: "numeric",
        required: true,
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
};
