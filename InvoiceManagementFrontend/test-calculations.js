import { test } from 'node:test';
import assert from 'node:assert';

function formatCurrency(amount, symbol = '₹') {
  const num = typeof amount === 'string' ? parseFloat(amount) : (amount || 0);
  if (isNaN(num)) return `${symbol}0.00`;
  return `${symbol}${num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function calculateInvoice(items, discount = 0, charges = 0, amountPaid = 0) {
  let subtotal = 0;
  let totalGst = 0;

  for (const item of items) {
    const lineTotal = Math.round(item.price * item.quantity * 100) / 100;
    const gstAmount = Math.round((lineTotal * item.gst_percentage / 100) * 100) / 100;
    subtotal += lineTotal;
    totalGst += gstAmount;
  }

  subtotal = Math.round(subtotal * 100) / 100;
  totalGst = Math.round(totalGst * 100) / 100;
  const grandTotal = Math.round(Math.max(0, subtotal + totalGst - discount + charges) * 100) / 100;
  const balanceDue = Math.round(Math.max(0, grandTotal - amountPaid) * 100) / 100;

  return { subtotal, totalGst, grandTotal, balanceDue };
}

test('Currency formatting with INR localization', () => {
  assert.strictEqual(formatCurrency(1000), '₹1,000.00');
  assert.strictEqual(formatCurrency(154700), '₹1,54,700.00');
  assert.strictEqual(formatCurrency(0), '₹0.00');
});

test('Line items and GST calculations', () => {
  const items = [
    { price: 45000, quantity: 2, gst_percentage: 18 }, // 90000 + 16200
    { price: 12500, quantity: 1, gst_percentage: 12 }, // 12500 + 1500
  ];
  const calc = calculateInvoice(items, 1000, 500, 50000);

  assert.strictEqual(calc.subtotal, 102500);
  assert.strictEqual(calc.totalGst, 17700);
  // 102500 + 17700 - 1000 + 500 = 119700
  assert.strictEqual(calc.grandTotal, 119700);
  // 119700 - 50000 = 69700
  assert.strictEqual(calc.balanceDue, 69700);
});

test('Overpayment and zero amounts safety', () => {
  const items = [{ price: 100, quantity: 1, gst_percentage: 0 }];
  const calc = calculateInvoice(items, 200, 0, 50); // discount > subtotal
  assert.strictEqual(calc.grandTotal, 0);
  assert.strictEqual(calc.balanceDue, 0);
});

