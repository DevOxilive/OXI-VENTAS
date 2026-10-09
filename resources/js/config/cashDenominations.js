export const cashDenominations = [
  { key: "1000", label: "$1000", value: 1000, group: "Billetes" },
  { key: "500", label: "$500", value: 500, group: "Billetes" },
  { key: "200", label: "$200", value: 200, group: "Billetes" },
  { key: "100", label: "$100", value: 100, group: "Billetes" },
  { key: "50", label: "$50", value: 50, group: "Billetes" },
  { key: "20b", label: "$20", value: 20, group: "Billetes" },
  { key: "20m", label: "$20", value: 20, group: "Monedas" },
  { key: "10", label: "$10", value: 10, group: "Monedas" },
  { key: "5", label: "$5", value: 5, group: "Monedas" },
  { key: "2", label: "$2", value: 2, group: "Monedas" },
  { key: "1", label: "$1", value: 1, group: "Monedas" },
  { key: "0.5", label: "$0.50", value: 0.5, group: "Monedas" },
];

export function totalCashDenominations(breakdown = {}) {
  return cashDenominations.reduce(
    (total, item) => total + Number(breakdown[item.key] || 0) * item.value,
    0,
  );
}
