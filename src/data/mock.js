export const transactions = [
  {
    transaction_id: "TXN1001",
    site_id: 1,
    site_title: "London Central",
    complete_datetime: "2026-03-20 12:30:00",
    complete_date: "2026-03-20",
    complete_time: "12:30:00",
    order_type: "Dine-In",
    channel_id: 1,
    channel_name: "POS",
    clerk_id: 101,
    clerk_name: "John",
    customer_name: "Alice",
    eat_in: 1,
    item_qty: 3,
    subtotal: 25.0,
    discounts: 2.5,
    tax: 4.5,
    service_charge: 2.0,
    total: 29.0,
    order_ref: "ORD001",
    table_number: "T1",
    table_covers: 2,
    complete: 1,
    canceled: 0
  },
  {
    transaction_id: "TXN1002",
    site_id: 1,
    site_title: "London Central",
    complete_datetime: "2026-03-20 14:10:00",
    complete_date: "2026-03-20",
    complete_time: "14:10:00",
    order_type: "Takeaway",
    channel_name: "Uber Eats",
    clerk_name: "Emma",
    eat_in: 0,
    item_qty: 2,
    subtotal: 18.0,
    discounts: 0,
    tax: 3.0,
    service_charge: 0,
    total: 21.0,
    complete: 1,
    canceled: 0
  },
  {
    transaction_id: "TXN1003",
    site_id: 2,
    site_title: "Manchester",
    complete_datetime: "2026-03-21 19:00:00",
    complete_date: "2026-03-21",
    complete_time: "19:00:00",
    order_type: "Delivery",
    channel_name: "Deliveroo",
    clerk_name: "David",
    eat_in: 0,
    item_qty: 5,
    subtotal: 40.0,
    discounts: 5.0,
    tax: 6.0,
    service_charge: 3.0,
    total: 44.0,
    complete: 1,
    canceled: 0
  },
  {
    transaction_id: "TXN1004",
    site_id: 1,
    site_title: "London Central",
    complete_datetime: "2026-03-21 10:00:00",
    complete_date: "2026-03-21",
    complete_time: "10:00:00",
    order_type: "Dine-In",
    channel_name: "POS",
    clerk_name: "John",
    eat_in: 1,
    item_qty: 1,
    subtotal: 10.0,
    discounts: 0,
    tax: 1.5,
    service_charge: 1.0,
    total: 12.5,
    complete: 1,
    canceled: 0
  },
  {
    transaction_id: "TXN1005",
    site_id: 2,
    site_title: "Manchester",
    complete_datetime: "2026-03-22 13:45:00",
    complete_date: "2026-03-22",
    complete_time: "13:45:00",
    order_type: "Takeaway",
    channel_name: "POS",
    clerk_name: "Emma",
    eat_in: 0,
    item_qty: 4,
    subtotal: 30.0,
    discounts: 3.0,
    tax: 4.0,
    service_charge: 0,
    total: 31.0,
    complete: 1,
    canceled: 0
  }
];

export const items = [
  {
    transaction_id: "TXN-001",
    product_id: 501,
    product_title: "Burger",
    category_name: "Food",
    quantity: 2,
    price: 5,
    tax: 1,
    total: 12
  },
  {
    transaction_id: "TXN-001",
    product_id: 502,
    product_title: "Fries",
    category_name: "Food",
    quantity: 2,
    price: 3,
    tax: 0.6,
    total: 7.2
  },
  {
    transaction_id: "TXN-002",
    product_id: 503,
    product_title: "Coffee",
    category_name: "Drinks",
    quantity: 2,
    price: 4,
    tax: 0.8,
    total: 9.6
  }
];

export const payments = [
  {
    transaction_id: "TXN-001",
    payment_type: "card",
    amount: 127,
    gratuity: 5,
    cashback: 0
  },
  {
    transaction_id: "TXN-002",
    payment_type: "cash",
    amount: 90,
    gratuity: 0,
    cashback: 0
  },
  {
    transaction_id: "TXN-003",
    payment_type: "card",
    amount: 208,
    gratuity: 12,
    cashback: 0
  }
];

export const sites = [
  { site_id: 1, site_name: "London Central", entity_id: 1 },
  { site_id: 2, site_name: "Oxford Street", entity_id: 1 }
];

export const clerks = [
  { clerk_id: 11, clerk_name: "John Smith", site_id: 1 },
  { clerk_id: 12, clerk_name: "Sarah Lee", site_id: 1 }
];
