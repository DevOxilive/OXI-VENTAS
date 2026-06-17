export const toolbarActionVariants = {
    primary: "bg-slate-900 hover:bg-slate-800 text-white",
    blue: "bg-blue-600 hover:bg-blue-700 text-white",
    green: "bg-green-600 hover:bg-green-700 text-white",
    red: "bg-red-600 hover:bg-red-700 text-white",
    amber: "bg-amber-500 hover:bg-amber-600 text-white",
    slate: "bg-slate-100 hover:bg-slate-200 text-slate-700",
    purple: "bg-purple-600 hover:bg-purple-700 text-white",
};

export function getToolbarActionClasses(variant = "primary") {
    return toolbarActionVariants[variant] || toolbarActionVariants.primary;
}

export default {
    toolbarActionVariants,
    getToolbarActionClasses,
};
