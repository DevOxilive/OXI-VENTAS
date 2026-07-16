import { execFileSync } from 'node:child_process'
import { existsSync, readFileSync } from 'node:fs'
import { extname } from 'node:path'

const binaryExtensions = new Set([
    '.dll',
    '.eot',
    '.exe',
    '.gif',
    '.ico',
    '.jpeg',
    '.jpg',
    '.pdf',
    '.png',
    '.sqlite',
    '.ttf',
    '.webp',
    '.woff',
    '.woff2',
    '.xlsx',
    '.zip',
])

const decoder = new TextDecoder('utf-8', { fatal: true })
const projectFiles = execFileSync('git', [
    'ls-files',
    '--cached',
    '--others',
    '--exclude-standard',
    '-z',
])
    .toString('utf8')
    .split('\0')
    .filter(Boolean)

const problems = []

function isTextFile(file) {
    return !binaryExtensions.has(extname(file).toLowerCase())
}

function addProblem(file, message, line = null) {
    problems.push(`${file}${line ? `:${line}` : ''} - ${message}`)
}

for (const file of projectFiles) {
    if (!existsSync(file) || !isTextFile(file)) continue

    const bytes = readFileSync(file)

    if (bytes.length >= 3 && bytes[0] === 0xef && bytes[1] === 0xbb && bytes[2] === 0xbf) {
        addProblem(file, 'contiene marca BOM; debe guardarse como UTF-8 sin BOM')
    }

    let content

    try {
        content = decoder.decode(bytes)
    } catch {
        addProblem(file, 'no contiene UTF-8 válido')
        continue
    }

    content.split(/\r?\n/u).forEach((line, index) => {
        if (/\u00c3[\u0080-\u00bf]|\u00c2[\u0080-\u00bf]|\u00e2\u20ac|\ufffd/u.test(line)) {
            addProblem(file, 'contiene caracteres asociados con texto UTF-8 mal convertido', index + 1)
        }

        if (!/https?:\/\//u.test(line) && /\p{L}\?\p{L}/u.test(line)) {
            addProblem(file, 'contiene un signo ? dentro de una palabra', index + 1)
        }

        if (/\u003fDeseas|S\u003f,/u.test(line)) {
            addProblem(file, 'parece haber perdido signos o acentos del español', index + 1)
        }
    })
}

if (problems.length > 0) {
    console.error('Se encontraron problemas de codificación:')
    problems.forEach((problem) => console.error(`- ${problem}`))
    process.exit(1)
}

console.log(`Codificación válida: ${projectFiles.length} archivos del proyecto revisados.`)
