// resources/js/Validation/sanitizers.js

const patterns = {
    letters: /[^\p{L}\s]/gu,
    numeric: /[^0-9]/g,
    decimal: /[^0-9.]/g,
    alphanumeric: /[^\p{L}\p{N}\-_\s]/gu,
    email: /[\s]/g,
    address: /[^\p{L}\p{N}\s#.,\-]/gu,
    rfc: /[^\p{L}\p{N}&]/gu,
    text: /[^\p{L}\p{N}\s.,;:?!()#%$+\-_/]/gu,
};

function toTitleCase(text) {
    return text
        .toLowerCase()
        .replace(/(^|[\s([{])(\p{L})/gu, (match, separator, letter) => (
            `${separator}${letter.toUpperCase()}`
        ));
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

        const hasDecimalPoint = clean.includes(".");
        const [integerPart = "", decimalPart = ""] = clean.split(".");
        const boundedInteger = config.maxIntegerDigits
            ? integerPart.slice(0, config.maxIntegerDigits)
            : integerPart;
        const boundedDecimal = config.maxDecimalDigits !== undefined
            ? decimalPart.slice(0, config.maxDecimalDigits)
            : decimalPart;

        clean = hasDecimalPoint
            ? `${boundedInteger || "0"}.${boundedDecimal}`
            : boundedInteger;
    }

    if (config.uppercase && !config.preserveCase) {
        clean = clean.toUpperCase();
    }

    if (config.titleCase && !config.uppercase && !config.preserveCase) {
        clean = toTitleCase(clean);
    }

    if (config.max) {
        clean = clean.slice(0, config.max);
    }

    return clean;
}
