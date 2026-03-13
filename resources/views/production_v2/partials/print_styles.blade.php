<style>
    body {
        font-family: Arial, sans-serif;
        color: #111827;
        margin: 0;
        background: #f3f4f6;
    }
    .sheet {
        max-width: 1040px;
        margin: 0 auto;
        padding: 24px;
        background: #fff;
    }
    .toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding: 12px 16px;
        background: #111827;
        color: #fff;
    }
    .toolbar button,
    .toolbar a {
        border: 0;
        background: #fff;
        color: #111827;
        padding: 8px 12px;
        border-radius: 6px;
        text-decoration: none;
        cursor: pointer;
        font-size: 14px;
    }
    .title {
        font-size: 22px;
        font-weight: 700;
        margin: 0 0 4px;
    }
    .subtitle {
        color: #6b7280;
        font-size: 13px;
        margin: 0;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
    }
    .label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
        margin-bottom: 4px;
    }
    .value {
        font-size: 14px;
    }
    .section {
        margin-top: 20px;
    }
    .section h2 {
        font-size: 15px;
        margin: 0 0 10px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        border: 1px solid #d1d5db;
        padding: 8px 10px;
        vertical-align: top;
        font-size: 12px;
    }
    th {
        background: #f9fafb;
        text-align: left;
    }
    .text-end {
        text-align: right;
    }
    .muted {
        color: #6b7280;
        font-size: 11px;
    }
    @media print {
        body {
            background: #fff;
        }
        .toolbar {
            display: none;
        }
        .sheet {
            max-width: none;
            padding: 0;
        }
        @page {
            size: A4;
            margin: 12mm;
        }
    }
</style>
