<?php

namespace Modules\Legal\Services;

use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Legal\Models\Client;

/**
 * منطق أعمال العملاء (Legal / LC-1): إنشاء/تعديل/تغيير الحالة — بلا حذف فعلي.
 */
class ClientService
{
    use RecordsAudit;

    public function create(array $data, Request $request): Client
    {
        $data['created_by'] = $request->user()?->id;
        $client = Client::create($data);
        $this->recordAudit($request, 'client_created', Client::class, $client->id, ['name' => $client->name]);

        return $client;
    }

    public function update(Client $client, array $data, Request $request): Client
    {
        $client->update($data);
        $this->recordAudit($request, 'client_updated', Client::class, $client->id);

        return $client;
    }

    /** تفعيل/تعطيل العميل (بديل الحذف الفعلي). */
    public function setStatus(Client $client, string $status, Request $request): Client
    {
        $client->update(['status' => $status]);
        $this->recordAudit($request, 'client_status_changed', Client::class, $client->id, ['status' => $status]);

        return $client;
    }
}
