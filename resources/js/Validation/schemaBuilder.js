// resources/js/Validation/schemaBuilder.js

import { z } from 'zod'
import { fieldRegistry } from './fieldRegistry'
import { buildZodField } from './zodRules'

export function buildSchema(fields) {
    const shape = {}

    fields.forEach(field => {
        const config = fieldRegistry[field]

        if (!config) {
            console.warn(`No existe configuración para el campo: ${field}`)
            return
        }

        shape[field] = buildZodField(config)
    })

    return z.object(shape)
}

export function validateSingleField(field, value) {
    const config = fieldRegistry[field]

    if (!config) {
        return ''
    }

    const result = buildZodField(config).safeParse(value)

    return result.success
        ? ''
        : result.error.issues[0]?.message || 'Campo inválido.'
}

export function validateForm(fields, data) {
    const schema = buildSchema(fields)
    const result = schema.safeParse(data)

    if (result.success) {
        return {}
    }

    const errors = {}

    result.error.issues.forEach(issue => {
        const field = issue.path[0]
        errors[field] = issue.message
    })

    return errors
}