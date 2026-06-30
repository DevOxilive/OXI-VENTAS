// resources/js/Config/Toolbars/usersToolbarConfig.js

export function getUsersToolbarConfig({ viewMode }) {
    return {
        showSearch: true,
        showRecordsPerPage: true,
        searchPlaceholder:
            viewMode === "users"
                ? "Buscar usuario por nombre, correo o rol..."
                : "Buscar empleado por nombre, correo o puesto...",
    };
}
