import { useForm, router } from "@inertiajs/vue3";
import { reactive, computed, watch } from "vue";
import {
    WarningAlert,
} from "@/Components/Modales/UniversalActionModal";
import { getModalRequestOptions } from "@/Components/Modales/useModalConfig";
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
        hasImss: false,
        nss: "",
        rfc: "",
    });

    const frontendErrors = reactive({});

    function normalizeRfcText(value = "") {
        return value
            .toString()
            .toUpperCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/[^A-ZÑ&\s]/g, " ")
            .replace(/\s+/g, " ")
            .trim();
    }

    function getFirstInternalVowel(word = "") {
        return word.slice(1).match(/[AEIOU]/)?.[0] || "X";
    }

    function getRelevantGivenName(words = []) {
        const ignoredNames = new Set(["JOSE", "J", "MARIA", "MA", "MA."]);
        return words.find((word) => !ignoredNames.has(word)) || words[0] || "";
    }

    function buildRfcNamePrefix(firstName = "", lastName = "") {
        const normalizedFirstName = normalizeRfcText(firstName);
        const normalizedLastName = normalizeRfcText(lastName);

        if (!normalizedFirstName && !normalizedLastName) {
            return "";
        }

        const firstNameParts = normalizedFirstName.split(" ").filter(Boolean);
        const lastNameParts = normalizedLastName.split(" ").filter(Boolean);

        const paternalSurname = lastNameParts[0] || "";
        const maternalSurname = lastNameParts[1] || "";
        const relevantFirstName = getRelevantGivenName(firstNameParts);

        const prefix = [
            paternalSurname[0] || "X",
            getFirstInternalVowel(paternalSurname),
            maternalSurname[0] || "X",
            relevantFirstName[0] || "X",
        ].join("");

        return prefix.slice(0, 4);
    }

    function syncRfcPrefix() {
        if (props.mode === "view") return;

        const prefix = buildRfcNamePrefix(employee.firstName, employee.lastName);
        const currentRfc = (employee.rfc || "").toUpperCase().replace(/[^A-Z0-9Ñ&]/g, "");
        const suffix = currentRfc.slice(4);
        const nextRfc = `${prefix}${suffix}`.slice(0, 13);

        if (employee.rfc !== nextRfc) {
            employee.rfc = nextRfc;
        }
    }

    function loadEditData() {
        const isEditing = ["edit", "view"].includes(props.mode);

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
            hasImss: Boolean(props.employeeToEdit.nss),
            nss: props.employeeToEdit.nss || "",
            rfc: props.employeeToEdit.rfc || "",
        });

        employee.reset();
    }

    function validateField(field) {
        if (!field) return;

        if (field === "rfc") {
            const value = (employee.rfc || "").toUpperCase();

            if (!value) {
                frontendErrors.rfc = "Este campo es obligatorio.";
                return;
            }

            if (value.length < 12) {
                frontendErrors.rfc =
                    "Completa el RFC con fecha y homoclave.";
                return;
            }
        }

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
        () => [employee.firstName, employee.lastName],
        () => {
            syncRfcPrefix();
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
        const isCreating = props.mode === "create";

        if (!employee.hasImss) {
            employee.nss = "";
            frontendErrors.nss = "";
        }

        if (employee.hasImss && !employee.nss) {
            frontendErrors.nss =
                "El NSS es obligatorio si el empleado está dado de alta en IMSS.";

            WarningAlert({
                title: "Formulario incompleto",
                message:
                    "Debes capturar el NSS o marcar que no está dado de alta en IMSS",
            });

            return;
        }

        if (!validateCompleteForm()) {
            WarningAlert({
                title: "Formulario incompleto",
                message:
                    "Debes corregir los campos marcados antes de continuar",
            });

            return;
        }

        const requestOptions = getModalRequestOptions({
            mode: isCreating ? "create" : "update",
            entityName: "Empleado",
            close: () => emit("close"),
            successTitle: isCreating
                ? "Empleado registrado correctamente"
                : "Empleado actualizado correctamente",
            errorTitle: "Error en la operaci?n",
            errorMessage: isCreating
                ? "No fue posible registrar el empleado"
                : "No fue posible actualizar el empleado",
            onSuccess: () => {
                employee.reset();
                clearFrontendErrors();

                router.reload({
                    only: ["employeesDB"],
                    preserveScroll: true,
                });
            },
        });

        isCreating
            ? employee.post(route("human-resources.employees.store"), requestOptions)
            : employee.put(
                  route("human-resources.employees.update", props.employeeToEdit.id),
                  requestOptions,
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
