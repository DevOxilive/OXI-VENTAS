export const toolbarActionVariants = {
    primary: "border border-primary bg-primary text-white hover:brightness-110",
    blue: "border border-primary bg-primary text-white hover:brightness-110",
    green: "border border-accent bg-accent text-background hover:brightness-105",
    red: "border border-primary bg-primary text-white hover:brightness-110",
    amber: "border border-accent bg-accent text-background hover:brightness-105",
    slate: "border border-secondary bg-secondary text-text hover:brightness-95",
    purple: "border border-primary bg-primary text-white hover:brightness-110",
};

export function getToolbarActionClasses(variant = "primary") {
    return toolbarActionVariants[variant] || toolbarActionVariants.primary;
}

export default {
    toolbarActionVariants,
    getToolbarActionClasses,
};
