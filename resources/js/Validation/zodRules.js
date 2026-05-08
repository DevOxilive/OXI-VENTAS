// resources/js/Validation/zodRules.js

import { z } from "zod";

const regex = {
    letters: /^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/,
    numeric: /^[0-9]+$/,
    decimal: /^\d+(\.\d{1,2})?$/,
    alphanumeric: /^[A-Za-z0-9ÁÉÍÓÚáéíóúÑñ\-_\s]+$/,
    address: /^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9\s#.,\-]+$/,
    rfc: /^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/,
};

function normalizeText(value) {
    return value
        .toString()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim();
}

function detectSpamText(value) {
    if (!value) return false;

    const original = value.toString().trim();
    const texto = normalizeText(original).replace(/\s+/g, "");
    const palabras = normalizeText(original).split(/\s+/).filter(Boolean);

    if (texto.length < 3) return false;

    // aaaa, kkkkk, 11111
    if (/^(.)\1{3,}$/.test(texto)) return true;

    // aaa, oooo, xxxx dentro de una palabra
    if (/(.)\1{3,}/i.test(texto)) return true;

    // vocales exageradas: shaaariitooo, piiichardo
    if (/[aeiou]{4,}/i.test(texto)) return true;

    // bloques repetidos: asdasd, qweqwe, abcabcabc
    if (/([a-z0-9]{2,5})\1{1,}/i.test(texto)) return true;

    // teclado/pruebas evidentes
    if (
        /(asd|qwe|zxc|wer|sdf|xcv|jkl|hjkl|test|prueba|fake|dummy)/i.test(texto)
    )
        return true;

    // demasiadas consonantes seguidas
    if (/[bcdfghjklmnpqrstvwxyz]{7,}/i.test(texto)) return true;

    const letras = texto.match(/[a-zñ]/gi) || [];
    const vocales = texto.match(/[aeiou]/gi) || [];
    const consonantes = texto.match(/[bcdfghjklmnpqrstvwxyzñ]/gi) || [];

    // texto largo con casi nada de vocales
    if (letras.length >= 8 && vocales.length / letras.length < 0.22)
        return true;

    // texto largo con demasiadas consonantes
    if (letras.length >= 12 && consonantes.length / letras.length > 0.78)
        return true;

    // CamelCase ridículo: ShAAARiiToOoOo
    const mixedCaseChunks =
        original.match(
            /[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+[A-ZÁÉÍÓÚÑ]|[a-záéíóúñ][A-ZÁÉÍÓÚÑ][a-záéíóúñ]/g,
        ) || [];
    if (mixedCaseChunks.length >= 2) return true;

    // demasiadas mayúsculas internas, pero permite "Juan Pérez"
    const chars = original.replace(/\s+/g, "");
    const internalUppercase = chars.slice(1).match(/[A-ZÁÉÍÓÚÑ]/g) || [];
    if (chars.length >= 8 && internalUppercase.length >= 3) return true;

    // palabras con patrón visual de burla: shaaaritooo, pichardoooo
    for (const palabra of palabras) {
        if (palabra.length >= 6 && /([aeiou])\1{2,}/i.test(palabra))
            return true;
        if (palabra.length >= 6 && /([a-zñ])\1{3,}/i.test(palabra)) return true;

        const pLetras = palabra.match(/[a-zñ]/gi) || [];
        const pVocales = palabra.match(/[aeiou]/gi) || [];

        if (pLetras.length >= 8 && pVocales.length / pLetras.length < 0.2)
            return true;
    }

    // Palabra real + cola basura: Guadalupefkvmruvmfiubltov
    for (const palabra of palabras) {
        if (palabra.length >= 12) {
            const consonantesFinales = palabra.match(
                /[bcdfghjklmnpqrstvwxyz]{5,}$/i,
            );

            if (consonantesFinales) {
                return true;
            }
        }
    }
    return false;
}

function detectSoftSpamText(value) {
    if (!value) return false;

    const original = value.toString().trim();
    const texto = normalizeText(original).replace(/\s+/g, "");

    if (texto.length < 4) return false;

    // Repetición absurda: aaaaa, 11111, kkkkk
    if (/^(.)\1{4,}$/.test(texto)) return true;

    // Letras repetidas exageradas dentro del texto
    if (/(.)\1{5,}/i.test(texto)) return true;

    // Bloques repetidos muy evidentes: asdasdasd, qweqweqwe
    if (/([a-z0-9]{2,5})\1{2,}/i.test(texto)) return true;

    // Palabras claramente de prueba
    if (/(asd|qwe|zxc|test|prueba|fake|dummy)/i.test(texto)) return true;

    // Demasiadas consonantes seguidas, más tolerante que el detector fuerte
    if (/[bcdfghjklmnpqrstvwxyz]{9,}/i.test(texto)) return true;

    return false;
}

function detectSpamEmail(value) {
    if (!value || !value.includes("@")) return false;

    const [local, domainPart] = value.toLowerCase().split("@");
    const domain = domainPart?.split(".")[0] || "";

    if (!local || !domain) return true;

    if (detectSpamText(local)) return true;
    if (detectSpamText(domain)) return true;

    // Correos evidentemente de prueba
    if (/(test|prueba|fake|correo|email|asd|qwe|zxc)/i.test(local)) return true;
    if (/(test|prueba|fake|asd|qwe|zxc)/i.test(domain)) return true;

    return false;
}

export function buildZodField(config) {
    let rule = z.string();

    if (config.min) {
        rule = rule.min(config.min, config.message);
    }

    if (config.max) {
        rule = rule.max(config.max, `Máximo ${config.max} caracteres.`);
    }

    if (config.type === "email") {
        rule = rule
            .email(config.message)
            .refine((value) => !detectSpamEmail(value), {
                message: config.spamMessage || "Correo no válido.",
            });
    }

    if (config.type === "date") {
        rule = rule.refine(
            (value) => {
                if (!value) return false;

                const today = new Date().toISOString().split("T")[0];
                return value <= today;
            },
            {
                message: config.message,
            },
        );
    }

    if (regex[config.type]) {
        rule = rule.regex(regex[config.type], config.message);
    }

    if (config.preventSpam) {
        rule = rule.refine(
            (value) => {
                if (config.spamLevel === "soft") {
                    return !detectSoftSpamText(value);
                }

                return !detectSpamText(value);
            },
            {
                message: config.spamMessage || "Texto no válido.",
            },
        );
    }

    if (config.required) {
        rule = rule.min(1, "Este campo es obligatorio.");
    } else {
        rule = rule.optional().or(z.literal(""));
    }

    return rule;
}
