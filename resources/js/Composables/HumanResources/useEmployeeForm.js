import { useForm } from "@inertiajs/vue3";
import { reactive, computed, watch } from "vue";
import {
    WarningAlert,
} from "@/Components/Modales/UniversalActionModal";
import { getModalRequestOptions } from "@/Components/Modales/useModalConfig";
import { validateSingleField, validateForm } from "@/Validation/schemaBuilder";

const employeeFields = [
    "firstName",
    "lastName",
    "birthDate",
    "positionId",
    "departmentId",
    "email",
    "phone",
    "emergencyContactName",
    "emergencyContactRelationship",
    "emergencyContactPhone",
    "secondaryEmergencyContactName",
    "secondaryEmergencyContactRelationship",
    "secondaryEmergencyContactPhone",
    "street",
    "externalNumber",
    "internalNumber",
    "postalCode",
    "neighborhood",
    "municipality",
    "addressState",
    "mapsUrl",
    "bank",
    "educationLevel",
    "specialty",
    "contractType",
    "nss",
    "rfc",
];

export function useEmployeeForm(props, emit) {
    const employee = useForm({
        firstName: "",
        lastName: "",
        birthDate: "",
        positionId: "",
        departmentId: "",
        email: "",
        phone: "",
        emergencyContactName: "",
        emergencyContactRelationship: "",
        emergencyContactPhone: "",
        secondaryEmergencyContactName: "",
        secondaryEmergencyContactRelationship: "",
        secondaryEmergencyContactPhone: "",
        street: "",
        externalNumber: "",
        internalNumber: "",
        postalCode: "",
        neighborhood: "",
        municipality: "",
        addressState: "",
        mapsUrl: "",
        startDate: "",
        employmentStatus: "Activo",
        bank: "HSBC",
        accountNumber: "",
        bankClabe: "",
        bankCardNumber: "",
        educationLevel: "",
        specialty: "",
        contractType: "",
        seniority: "",
        hasImss: false,
        nss: "",
        rfc: "",
        record_version: "",
    });

    const allDepartments = computed(
        () => props.organizationOptions?.departments || [],
    );
    const allPositions = computed(
        () => props.organizationOptions?.positions || [],
    );
    const departments = computed(() => allDepartments.value.filter((department) => (
        department.active
        || Number(department.value) === Number(employee.departmentId)
    )));
    const positions = computed(() => allPositions.value.filter((position) => (
        Number(position.departmentId) === Number(employee.departmentId)
        && (
            position.active
            || Number(position.value) === Number(employee.positionId)
        )
    )));

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

    function formatBirthDateForRfc(value = "") {
        const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})$/);

        if (!match) return "";

        return `${match[1].slice(2)}${match[2]}${match[3]}`;
    }

    function localDateFromInput(value = "") {
        const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})$/);

        if (!match) return null;

        return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    }

    const age = computed(() => {
        const birthDate = localDateFromInput(employee.birthDate);

        if (!birthDate) return "";

        const today = new Date();
        let years = today.getFullYear() - birthDate.getFullYear();
        const birthdayAlreadyPassed =
            today.getMonth() > birthDate.getMonth()
            || (
                today.getMonth() === birthDate.getMonth()
                && today.getDate() >= birthDate.getDate()
            );

        if (!birthdayAlreadyPassed) {
            years--;
        }

        return years >= 0 ? `${years} años` : "";
    });

    function syncRfcPrefix() {
        if (props.mode === "view") return;

        const prefix = buildRfcNamePrefix(employee.firstName, employee.lastName);
        const birthDateSegment = formatBirthDateForRfc(employee.birthDate);
        const currentRfc = (employee.rfc || "").toUpperCase().replace(/[^A-Z0-9Ñ&]/g, "");
        const suffix = currentRfc.slice(10);
        const nextRfc = `${prefix}${birthDateSegment}${suffix}`.slice(0, 13);

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
            birthDate: props.employeeToEdit.birthDate || "",
            positionId: props.employeeToEdit.positionId
                ? String(props.employeeToEdit.positionId)
                : "",
            departmentId: props.employeeToEdit.departmentId
                ? String(props.employeeToEdit.departmentId)
                : "",
            email: props.employeeToEdit.email || "",
            phone: props.employeeToEdit.phone || "",
            emergencyContactName: props.employeeToEdit.emergencyContactName || "",
            emergencyContactRelationship: props.employeeToEdit.emergencyContactRelationship || "",
            emergencyContactPhone: props.employeeToEdit.emergencyContactPhone || "",
            secondaryEmergencyContactName: props.employeeToEdit.secondaryEmergencyContactName || "",
            secondaryEmergencyContactRelationship: props.employeeToEdit.secondaryEmergencyContactRelationship || "",
            secondaryEmergencyContactPhone: props.employeeToEdit.secondaryEmergencyContactPhone || "",
            street: props.employeeToEdit.street || "",
            externalNumber: props.employeeToEdit.externalNumber || "",
            internalNumber: props.employeeToEdit.internalNumber || "",
            postalCode: props.employeeToEdit.postalCode || "",
            neighborhood: props.employeeToEdit.neighborhood || "",
            municipality: props.employeeToEdit.municipality || "",
            addressState: props.employeeToEdit.addressState || "",
            mapsUrl: props.employeeToEdit.mapsUrl || "",
            startDate: props.employeeToEdit.startDate || "",
            employmentStatus: props.employeeToEdit.employmentStatus || "Activo",
            bank: props.employeeToEdit.bank || "HSBC",
            accountNumber: props.employeeToEdit.accountNumber || "",
            bankClabe: props.employeeToEdit.bankClabe || "",
            bankCardNumber: props.employeeToEdit.bankCardNumber || "",
            educationLevel: props.employeeToEdit.educationLevel || "",
            specialty: props.employeeToEdit.specialty || "",
            contractType: props.employeeToEdit.contractType || "",
            seniority: props.employeeToEdit.seniority || "",
            hasImss: Boolean(props.employeeToEdit.nss),
            nss: props.employeeToEdit.nss || "",
            rfc: props.employeeToEdit.rfc || "",
            record_version: props.employeeToEdit.recordVersion || "",
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

            if (value.length < 13) {
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
        () => employee.departmentId,
        () => {
            if (!employee.positionId) return;

            const positionBelongsToDepartment = allPositions.value.some((position) => (
                Number(position.value) === Number(employee.positionId)
                && Number(position.departmentId) === Number(employee.departmentId)
            ));

            if (!positionBelongsToDepartment) {
                employee.positionId = "";
            }
        },
    );

    watch(
        () => employee.rfc,
        (newValue) => {
            if (!newValue) return;

            employee.rfc = newValue.toUpperCase();
        },
    );

    watch(
        () => [employee.firstName, employee.lastName, employee.birthDate],
        () => {
            syncRfcPrefix();
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
            errorTitle: "Error en la operación",
            errorMessage: isCreating
                ? "No fue posible registrar el empleado"
                : "No fue posible actualizar el empleado",
            onSuccess: () => {
                employee.reset();
                clearFrontendErrors();
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
        age,
        frontendErrors,
        departments,
        positions,
        errorSummary,
        validateField,
        saveEmployee,
        loadEditData,
    };
}
