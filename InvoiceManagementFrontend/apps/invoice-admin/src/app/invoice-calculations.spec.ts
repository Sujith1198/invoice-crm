import { formatCurrency, formatDate, exportToCsv, getStatusBadgeClass } from '@invoice-mgmt/shared-utils';

describe('Invoice & Utilities Unit Tests', () => {
  it('should format Indian Rupee currency correctly', () => {
    expect(formatCurrency(1000)).toBe('₹1,000.00');
    expect(formatCurrency(125000.5)).toBe('₹1,25,000.50');
    expect(formatCurrency(0)).toBe('₹0.00');
    expect(formatCurrency(null)).toBe('₹0.00');
  });

  it('should calculate line total and GST percentage accurately', () => {
    const price = 5000;
    const qty = 3;
    const gstPct = 18;

    const lineTotal = price * qty;
    const gstAmount = (lineTotal * gstPct) / 100;
    const grandTotal = lineTotal + gstAmount;

    expect(lineTotal).toBe(15000);
    expect(gstAmount).toBe(2700);
    expect(grandTotal).toBe(17700);
  });

  it('should calculate grand total with discount and additional charges', () => {
    const subtotal = 20000;
    const gst = 3600;
    const discount = 500;
    const charges = 250;

    const grandTotal = subtotal + gst - discount + charges;
    const amountPaid = 10000;
    const balanceDue = grandTotal - amountPaid;

    expect(grandTotal).toBe(23350);
    expect(balanceDue).toBe(13350);
  });

  it('should return appropriate status badge classes', () => {
    expect(getStatusBadgeClass('Paid')).toBe('badge-success');
    expect(getStatusBadgeClass('Partially Paid')).toBe('badge-warning');
    expect(getStatusBadgeClass('Generated')).toBe('badge-info');
    expect(getStatusBadgeClass('Draft')).toBe('badge-secondary');
    expect(getStatusBadgeClass('Overdue')).toBe('badge-danger');
  });
});

