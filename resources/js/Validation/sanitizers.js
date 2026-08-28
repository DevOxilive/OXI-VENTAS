// resources/js/Validation/sanitizers.js

const patterns = {
    letters: /[^\p{L}\s]/gu,
    numeric: /[^0-9]/g,
    decimal: /[^0-9.]/g,
    product_name: /[\p{C}]/gu,
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

function normalizeDecimalSeparators(value, config = {}) {
    const rawValue = String(value ?? "");

    if (config.format === "currency") {
        return rawValue.replace(/,/g, "");
    }

    if (!rawValue.includes(".") && rawValue.includes(",")) {
        return rawValue.replace(",", ".");
    }

    return rawValue.replace(/,/g, "");
}

function sanitizeDecimal(value, config = {}) {
    let clean = normalizeDecimalSeparators(value, config).replace(patterns.decimal, "");
    clean = clean.replace(/(\..*)\./g, "$1");

    if (
        config.autoDecimalAfterIntegerDigits
        && config.maxIntegerDigits
        && !clean.includes(".")
        && clean.length > config.maxIntegerDigits
    ) {
        clean = `${clean.slice(0, config.maxIntegerDigits)}.${clean.slice(config.maxIntegerDigits)}`;
    }

    const hasDecimalPoint = clean.includes(".");
    const [integerPart = "", decimalPart = ""] = clean.split(".");
    const boundedInteger = config.maxIntegerDigits
        ? integerPart.slice(0, config.maxIntegerDigits)
        : integerPart;
    const boundedDecimal = config.maxDecimalDigits !== undefined
        ? decimalPart.slice(0, config.maxDecimalDigits)
        : decimalPart;

    return hasDecimalPoint
        ? `${boundedInteger || "0"}.${boundedDecimal}`
        : boundedInteger;
}

export function sanitizeField(value, config = {}, options = {}) {
    if (value === null || value === undefined) return "";

    let clean = value.toString();

    const pattern = config.type === "decimal" ? null : patterns[config.type];

    if (pattern) {
        clean = clean.replace(pattern, "");
    }

    clean = clean.replace(/\s+/g, " ");
    clean = clean.trimStart();

    if (config.type === "decimal") {
        clean = sanitizeDecimal(clean, config);
    }

    if (options.formatCase !== false && config.uppercase && !config.preserveCase) {
        clean = clean.toUpperCase();
    }

    if (options.formatCase !== false && config.titleCase && !config.uppercase && !config.preserveCase) {
        clean = toTitleCase(clean);
    }

    if (options.enforceMax !== false && config.max) {
        clean = clean.slice(0, config.max);
    }

    return clean;
}

export function sanitizeFieldWithCursor(value, config = {}, selectionStart = 0, selectionEnd = selectionStart) {
    const rawValue = String(value ?? '');
    const safeStart = Math.max(0, Math.min(selectionStart, rawValue.length));
    const safeEnd = Math.max(safeStart, Math.min(selectionEnd, rawValue.length));
    const options = {
        formatCase: config.formatCaseOnInput !== false,
        enforceMax: config.enforceMaxOnInput === true,
    };
    const sanitized = sanitizeField(rawValue, config, options);

    return {
        value: sanitized,
        selectionStart: Math.min(
            sanitizeField(rawValue.slice(0, safeStart), config, options).length,
            sanitized.length,
        ),
        selectionEnd: Math.min(
            sanitizeField(rawValue.slice(0, safeEnd), config, options).length,
            sanitized.length,
        ),
    };
}

export function formatCurrencyValue(value, config = {}, options = {}) {
    const clean = sanitizeField(value, {
        ...config,
        type: "decimal",
        format: "currency",
    });

    if (!clean) return "";

    const hasDecimalPoint = clean.includes(".");
    const [integerPart = "", decimalPart = ""] = clean.split(".");
    const groupedInteger = (integerPart || "0").replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    const decimalDigits = config.maxDecimalDigits ?? 2;

    if (options.fixedDecimals) {
        return `${groupedInteger}.${decimalPart.padEnd(decimalDigits, "0").slice(0, decimalDigits)}`;
    }

    return hasDecimalPoint
        ? `${groupedInteger}.${decimalPart}`
        : groupedInteger;
}

export function sanitizeCurrencyWithCursor(value, config = {}, selectionStart = 0, selectionEnd = selectionStart) {
    const rawValue = String(value ?? "");
    const safeStart = Math.max(0, Math.min(selectionStart, rawValue.length));
    const safeEnd = Math.max(safeStart, Math.min(selectionEnd, rawValue.length));
    const currencyConfig = {
        ...config,
        type: "decimal",
        format: "currency",
    };
    const clean = sanitizeField(rawValue, currencyConfig);
    const cleanBeforeStart = sanitizeField(rawValue.slice(0, safeStart), currencyConfig);
    const cleanBeforeEnd = sanitizeField(rawValue.slice(0, safeEnd), currencyConfig);
    const displayValue = formatCurrencyValue(clean, currencyConfig);

    return {
        value: displayValue,
        rawValue: clean,
        selectionStart: Math.min(
            formatCurrencyValue(cleanBeforeStart, currencyConfig).length,
            displayValue.length,
        ),
        selectionEnd: Math.min(
            formatCurrencyValue(cleanBeforeEnd, currencyConfig).length,
            displayValue.length,
        ),
    };
}
