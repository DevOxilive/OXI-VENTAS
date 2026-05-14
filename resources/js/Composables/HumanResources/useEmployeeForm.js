import { useForm } from "@inertiajs/vue3";
import { reactive, computed, watch } from "vue";
import {
    WarningAlert,
    ToastAlert,
    ErrorAlert,
} from "@/Components/Modales/UniversalActionModal";
import { validateSingleField, validateForm } from "@/Validation/schemaBuilder";

const employeeFields = [
    "firstName",
    "lastName",
    "position",
    "department",
    "employmentStatus",
    "email",
    "phone",
    "street",
    "externalNumber",
    "internalNumber",
    "postalCode",
    "neighborhood",
    "municipality",
    "addressState",
    "mapsUrl",
    "startDate",
    "bank",
    "accountNumber",
    "educationLevel",
    "specialty",
    "contractType",
    "nss",
    "rfc",
];

export function useEmployeeForm(props, emit) {
    const departments = [
        "Inventario",
        "Sistemas",
        "Recursos Humanos",
        "Ventas",
    ];

    const employee = useForm({
        firstName: "",
        lastName: "",
        position: "",
        department: "",
        employmentStatus: "Activo",
        email: "",
        phone: "",
        street: "",
        externalNumber: "",
        internalNumber: "",
        postalCode: "",
        neighborhood: "",
        municipality: "",
        addressState: "",
        mapsUrl: "",
        startDate: "",
        bank: "",
        accountNumber: "",
        educationLevel: "",
        specialty: "",
        contractType: "",
        seniority: "",
        nss: "",
        rfc: "",
    });

    const frontendErrors = reactive({});

    function loadEditData() {
        const isEditing = ["edit", "view"].includes(props.modo);

        if (!isEditing || !props.employeeToEdit) return;

        employee.defaults({
            firstName: props.employeeToEdit.firstName || "",
            lastName: props.employeeToEdit.lastName || "",
            position: props.employeeToEdit.position || "",
            department: props.employeeToEdit.department || "",
            employmentStatus: props.employeeToEdit.employmentStatus || "Activo",
            email: props.employeeToEdit.email || "",
            phone: props.employeeToEdit.phone || "",
            street: props.employeeToEdit.street || "",
            externalNumber: props.employeeToEdit.externalNumber || "",
            internalNumber: props.employeeToEdit.internalNumber || "",
            postalCode: props.employeeToEdit.postalCode || "",
            neighborhood: props.employeeToEdit.neighborhood || "",
            municipality: props.employeeToEdit.municipality || "",
            addressState: props.employeeToEdit.addressState || "",
            mapsUrl: props.employeeToEdit.mapsUrl || "",
            startDate: props.employeeToEdit.startDate || "",
            bank: props.employeeToEdit.bank || "",
            accountNumber: props.employeeToEdit.accountNumber || "",
            educationLevel: props.employeeToEdit.educationLevel || "",
            specialty: props.employeeToEdit.specialty || "",
            contractType: props.employeeToEdit.contractType || "",
            seniority: props.employeeToEdit.seniority || "",
            nss: props.employeeToEdit.nss || "",
            rfc: props.employeeToEdit.rfc || "",
        });

        employee.reset();
    }

    function validateField(field) {
        if (!field) return;

        frontendErrors[field] = validateSingleField(field, employee[field]);
    }

    function validateCompleteForm() {
        employeeFields.forEach((field) => {
            frontendErrors[field] = "";
        });

        const errors = validateForm(employeeFields, employee.data());

        Object.entries(errors).forEach(([field, message]) => {
            frontendErrors[field] = message;
        });

        return Object.keys(errors).length === 0;
    }

    const errorSummary = computed(() =>
        Object.values(frontendErrors).filter((error) => error !== ""),
    );

    watch(
        () => employee.rfc,
        (newValue) => {
            if (!newValue) return;
            employee.rfc = newValue.toUpperCase();
        },
    );

    watch(
        () => employee.startDate,
        (newValue) => {
            if (!newValue) {
                employee.seniority = "";
                return;
            }

            const admissionDate = new Date(newValue);
            const today = new Date();

            let years = today.getFullYear() - admissionDate.getFullYear();
            let months = today.getMonth() - admissionDate.getMonth();

            if (months < 0) {
                years--;
                months += 12;
            }

            employee.seniority = `${years} años ${months} meses`;
        },
    );

    function clearFrontendErrors() {
        employeeFields.forEach((field) => {
            frontendErrors[field] = "";
        });
    }

    function saveEmployee() {
        const isCreating = props.modo === "create";

        if (!validateCompleteForm()) {
            WarningAlert({
                title: "Formulario incompleto",
                message:
                    "Debes corregir los campos marcados antes de continuar",
            });
            return;
        }

        const config = {
            onSuccess: () => {
                ToastAlert({
                    icon: "success",
                    title: isCreating
                        ? "Empleado registrado correctamente"
                        : "Empleado actualizado correctamente",
                });

                emit("close");
                employee.reset();
                clearFrontendErrors();
            },

            onError: () => {
                ErrorAlert({
                    title: "Error en la operación",
                    message: isCreating
                        ? "No fue posible registrar el empleado"
                        : "No fue posible actualizar el empleado",
                });
            },
        };

        isCreating
            ? employee.post(route("rh.empleados.store"), config)
            : employee.put(
                  route("rh.empleados.update", props.employeeToEdit.id),
                  config,
              );
    }

    return {
        employee,
        frontendErrors,
        departments,
        errorSummary,
        validateField,
        saveEmployee,
        loadEditData,
    };
}
