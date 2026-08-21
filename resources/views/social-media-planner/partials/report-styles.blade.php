<style>
    .smr-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-left: 5px solid #0f766e;
        border-radius: 16px;
        padding: 16px;
        box-sizing: border-box;
    }

    .smr-card.smr-teal { border-left-color: #0f766e; }
    .smr-card.smr-blue { border-left-color: #2563eb; }
    .smr-card.smr-violet { border-left-color: #7c3aed; }
    .smr-card.smr-amber { border-left-color: #d97706; }
    .smr-card.smr-rose { border-left-color: #e11d48; }
    .smr-card.smr-emerald { border-left-color: #059669; }
    .smr-card.smr-slate { border-left-color: #64748b; }
    .smr-card.smr-sky { border-left-color: #0284c7; }

    .smr-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .smr-icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        color: #0f766e;
        font-size: 16px;
        flex: 0 0 auto;
    }

    .smr-value {
        margin-top: 10px;
        color: #172033;
        font-size: 24px;
        font-weight: 900;
        line-height: 1.1;
    }

    .smr-label {
        margin-top: 3px;
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .smr-section-title {
        margin: 0;
        color: #172033;
        font-size: 15px;
        font-weight: 900;
    }

    .smr-table {
        width: 100%;
        border-collapse: collapse;
    }

    .smr-table th {
        background: #f8fafc;
        color: #64748b;
        font-size: 10px;
        font-weight: 900;
        text-align: left;
        padding: 10px;
        border-bottom: 1px solid #e2e8f0;
        text-transform: uppercase;
    }

    .smr-table td {
        color: #334155;
        font-size: 11px;
        padding: 10px;
        border-bottom: 1px solid #eef2f7;
        vertical-align: top;
    }

    .smr-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        font-size: 9px;
        font-weight: 800;
    }

    .smr-muted {
        color: #64748b;
    }

    .smr-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    @media (max-width: 1024px) {
        .smr-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .smr-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
