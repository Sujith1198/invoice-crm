export function formatCurrency(amount: number | string | null | undefined, symbol: string = '₹'): string {
  const num = typeof amount === 'string' ? parseFloat(amount) : (amount || 0);
  if (isNaN(num)) return `${symbol}0.00`;
  return `${symbol}${num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function formatDate(dateString: string | null | undefined): string {
  if (!dateString) return '-';
  const d = new Date(dateString);
  if (isNaN(d.getTime())) return dateString;
  return d.toLocaleDateString('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  });
}

export function exportToCsv(filename: string, headers: string[], rows: (string | number)[][]): void {
  const processCell = (cell: string | number) => {
    let str = cell === null || cell === undefined ? '' : String(cell);
    str = str.replace(/"/g, '""');
    if (str.search(/("|,|\n)/g) >= 0) {
      str = `"${str}"`;
    }
    return str;
  };

  const csvContent = [
    headers.map(processCell).join(','),
    ...rows.map(row => row.map(processCell).join(','))
  ].join('\r\n');

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', `${filename}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

export function getStatusBadgeClass(status: string): string {
  switch (status) {
    case 'Paid':
    case 'active':
      return 'badge-success';
    case 'Partially Paid':
      return 'badge-warning';
    case 'Generated':
      return 'badge-info';
    case 'Draft':
      return 'badge-secondary';
    case 'Overdue':
    case 'inactive':
      return 'badge-danger';
    case 'Cancelled':
      return 'badge-dark';
    default:
      return 'badge-secondary';
  }
}

export function getRoleBadgeClass(role: string): string {
  switch (role) {
    case 'admin':
      return 'bg-danger-subtle text-danger border border-danger-subtle';
    case 'manager':
      return 'bg-primary-subtle text-primary border border-primary-subtle';
    case 'team lead':
      return 'bg-warning-subtle text-dark border border-warning-subtle';
    case 'user':
      return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    default:
      return 'bg-light text-dark border';
  }
}


