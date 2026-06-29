import { modalPresets } from './modalPresets'

export const userDetailModalConfig = {
    mode: 'view',
    title: 'Detalle del usuario',
    subtitle: 'Información de acceso, rol, sucursales y permisos',
    totalErrors: 0,
    ...modalPresets.standard,
    showSave: false,
}
