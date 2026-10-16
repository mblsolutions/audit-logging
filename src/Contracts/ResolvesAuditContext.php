<?php

namespace MBLSolutions\AuditLogging\Contracts;

use Illuminate\Http\Request;

interface ResolvesAuditContext
{
    /**
     * Extra ids to store on the audit event, e.g. ['account_id' => 12, 'order_id' => 345].
     * Keep it cheap: it runs at the end of every audited request.
     *
     * @return array<string, scalar|null>
     */
    public function resolve(Request $request, $response, $user = null): array;
}
