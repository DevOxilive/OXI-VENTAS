// resources/js/Validation/sanitizers.js

const patterns = {
    letters: /[^A-Za-zÁÉÍÓÚáéíóúÑñ\s]/g,
    numeric: /[^0-9]/g,
    decimal: /[^0-9.]/g,
    alphanumeric: /[^A-Za-z0-9ÁÉÍÓÚáéíóúÑñ\-_\s]/g,
    email: /[\s]/g,
    address: /[^A-Za-zÁÉÍÓÚáéíóúÑñ0-9\s#.,\-]/g,
    rfc: /[^A-Za-z0-9Ññ&]/g,
    text: /[<>]/g,
};

function toTitleCase(text) {
    return text
        .toLowerCase()
        .split(" ")
        .map((word) => {
            if (!word) return "";

            return word.charAt(0).toUpperCase() + word.slice(1);
        })
        .join(" ");
}

export function sanitizeField(value, config = {}) {
    if (value === null || value === undefined) return "";

    let clean = value.toString();

    const pattern = patterns[config.type];

    if (pattern) {
        clean = clean.replace(pattern, "");
    }

    clean = clean.replace(/\s+/g, " ");
    clean = clean.trimStart();

    if (config.type === "decimal") {
        clean = clean.replace(/(\..*)\./g, "$1");
    }

    if (config.uppercase) {
        clean = clean.toUpperCase();
    }

    if (config.titleCase) {
        clean = toTitleCase(clean);
    }

    if (config.max) {
        clean = clean.slice(0, config.max);
    }

    return clean;
}
