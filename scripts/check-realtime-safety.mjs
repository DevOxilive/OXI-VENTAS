import fs from 'node:fs'
import path from 'node:path'
import process from 'node:process'

const root = process.cwd()
const resourcesDirectory = path.join(root, 'resources', 'js')
const sourceExtensions = new Set(['.js', '.vue'])
const violations = []

function visit(directory) {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const entryPath = path.join(directory, entry.name)

        if (entry.isDirectory()) {
            visit(entryPath)
            continue
        }

        if (!sourceExtensions.has(path.extname(entry.name))) continue

        const source = fs.readFileSync(entryPath, 'utf8')
        const relativePath = path.relative(root, entryPath)

        if (/router\s*\.\s*reload\s*\(/.test(source)) {
            violations.push(`${relativePath}: usa router.reload(), que puede reemplazar estado local`)
        }

        if (/(?:window\s*\.\s*)?location\s*\.\s*reload\s*\(/.test(source)) {
            violations.push(`${relativePath}: usa location.reload(), que elimina el progreso local`)
        }

        if (/refreshRealtimeProps\(\s*page\s*\)/.test(source)) {
            violations.push(`${relativePath}: solicita todas las propiedades de Inertia en tiempo real`)
        }
    }
}

visit(resourcesDirectory)

if (violations.length > 0) {
    console.error('Se detectaron recargas incompatibles con formularios colaborativos:')
    violations.forEach((violation) => console.error(`- ${violation}`))
    process.exit(1)
}

console.log('Realtime safety check passed.')
