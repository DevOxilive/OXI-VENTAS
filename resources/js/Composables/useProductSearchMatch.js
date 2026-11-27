const BARCODE_PATTERN = /^\d{5,}$/;

function productCodes(product) {
  const values = [
    product?.barcode,
    product?.code,
    product?.matched_code,
    product?.primary_code,
    ...(Array.isArray(product?.barcodes) ? product.barcodes : []),
    ...(Array.isArray(product?.related_codes) ? product.related_codes : []),
  ];

  return values
    .map((value) => String(value ?? "").trim().toLowerCase())
    .filter(Boolean);
}

export function isBarcodeQuery(query) {
  return BARCODE_PATTERN.test(String(query || "").trim());
}

export function resolveProductSearchSelection(query, products = [], exactProductIds = []) {
  const normalized = String(query || "").trim().toLowerCase();
  const candidates = Array.isArray(products) ? products : [];
  const exactIds = new Set((Array.isArray(exactProductIds) ? exactProductIds : []).map(String));
  const exactProduct = exactIds.size
    ? candidates.find((product) => exactIds.has(String(product.product_id ?? product.id))) || null
    : candidates.find((product) => productCodes(product).includes(normalized)) || null;

  return {
    isBarcodeQuery: isBarcodeQuery(query),
    exactProduct,
    canAutoSelect: Boolean(exactProduct),
  };
}
