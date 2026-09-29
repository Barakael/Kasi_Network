import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { Link } from 'react-router';
import { api } from '../api';
import { Badge, Card, Empty, ErrorBanner, Field, PageHeader, inputClass, primaryBtn } from '../components/ui';

export function CampaignsPage() {
  const client = useQueryClient();
  const campaigns = useQuery({ queryKey: ['campaigns'], queryFn: api.campaigns });
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({ title: '', body: '', audience: 'all' });

  const create = useMutation({
    mutationFn: () => api.createCampaign(form),
    onSuccess: () => {
      setForm({ title: '', body: '', audience: 'all' });
      void client.invalidateQueries({ queryKey: ['campaigns'] });
    },
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div>
      <PageHeader
        title="Notices"
        subtitle="Ujumbe kwenye portal. Kimya ni namba zilizonasa bila bundle hai — orodha iko kwenye Wateja."
      />
      <ErrorBanner message={error} />
      <Card className="mb-4">
        <form
          className="space-y-3"
          onSubmit={(event: FormEvent) => {
            event.preventDefault();
            create.mutate();
          }}
        >
          <Field label="Kichwa">
            <input className={inputClass} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required />
          </Field>
          <Field label="Ujumbe">
            <input className={inputClass} value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} required />
          </Field>
          <Field label="Walengwa">
            <select className={inputClass} value={form.audience} onChange={(e) => setForm({ ...form, audience: e.target.value })}>
              <option value="all">Wote kwenye portal</option>
              <option value="kimya">Kimya tu</option>
            </select>
          </Field>
          <button type="submit" className={primaryBtn} disabled={create.isPending}>
            Publish
          </button>
        </form>
      </Card>
      <div className="space-y-3">
        {(campaigns.data?.data ?? []).map((row) => (
          <Card key={row.id}>
            <div className="flex items-center justify-between gap-2">
              <div>
                <p className="font-semibold">{row.title}</p>
                <p className="text-sm text-ink-700">{row.body}</p>
                <p className="mt-1 text-xs text-ink-700">{row.audience === 'kimya' ? 'Kimya tu' : 'Wote'}</p>
              </div>
              <Badge tone={row.is_live ? 'green' : 'slate'}>{row.is_live ? 'Live' : 'Off'}</Badge>
            </div>
          </Card>
        ))}
      </div>
      {!campaigns.data?.data.length && (
        <Empty>
          {campaigns.isLoading ? (
            'Inapakia…'
          ) : (
            <>
              Hakuna notice bado.{' '}
              <Link to="/customers" className="font-semibold text-brand-700 hover:underline">
                Wateja Kimya
              </Link>
            </>
          )}
        </Empty>
      )}
    </div>
  );
}
