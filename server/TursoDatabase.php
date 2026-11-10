<?php
declare(strict_types=1);

// A transaction must reuse Turso's connection baton until commit or rollback.
final class TursoDatabase {
    private ?string $baton = null;
    private string $endpoint;
    private string $initialEndpoint;
    public function __construct(string $url, private string $token) {
        $url = preg_replace('~^(libsql|turso)://~', 'https://', $url);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !$token) {
            throw new RuntimeException('Check the Turso environment variables.');
        }
        $this->initialEndpoint = $this->endpoint = rtrim($url, '/') . '/v2/pipeline';
    }
    private function send(array $operations, bool $close): array {
        if ($close) $operations[] = ['type'=>'close'];
        $request = curl_init($this->endpoint);
        curl_setopt_array($request, [
            CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$this->token],
            CURLOPT_POSTFIELDS=>json_encode(['baton'=>$this->baton,'requests'=>$operations], JSON_THROW_ON_ERROR)
        ]);
        $response = curl_exec($request);
        if ($response === false || curl_getinfo($request, CURLINFO_RESPONSE_CODE) !== 200) {
            throw new RuntimeException('Turso HTTP '.curl_getinfo($request, CURLINFO_RESPONSE_CODE).' (curl '.curl_errno($request).')');
        }
        $body = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        $this->baton = $body['baton'] ?? null;
        if ($close) $this->endpoint = $this->initialEndpoint;
        elseif (!empty($body['base_url'])) {
            // A routing URL can carry a query string. Keep it after the endpoint path.
            [$base,$query]=array_pad(explode('?',$body['base_url'],2),2,null);
            $this->endpoint=rtrim($base,'/').'/v2/pipeline'.($query===null ? '' : '?'.$query);
        }
        foreach ($body['results'] as $result) {
            if ($result['type'] !== 'ok') throw new RuntimeException('Turso rejected a database operation.');
        }
        return $body['results'][0]['response'];
    }
    public function query(string $sql, array $params=[]): TursoResult {
        $args = [];
        foreach ($params as $value) {
            $args[] = ['type'=>is_int($value) ? 'integer' : 'text','value'=>(string)$value];
        }
        $op = ['type'=>'execute','stmt'=>['sql'=>$sql,'args'=>$args]];
        $reply = $this->send([$op], !$this->inTransaction());
        return new TursoResult($reply['result']);
    }
    public function beginTransaction(): void {
        $this->send([['type'=>'execute','stmt'=>['sql'=>'BEGIN IMMEDIATE']]], false);
    }
    public function commit(): void { $this->finish('COMMIT'); }
    public function rollBack(): void { $this->finish('ROLLBACK'); }
    private function finish(string $sql): void {
        $this->send([['type'=>'execute','stmt'=>['sql'=>$sql]]], true);
    }
    public function inTransaction(): bool { return $this->baton !== null; }
    public function exec(string $sql): void {
        $this->send([['type'=>'sequence','sql'=>$sql]], !$this->inTransaction());
    }
}

final class TursoResult {
    public function __construct(private array $result) {}
    public function fetchAll(): array {
        $names = array_column($this->result['cols'],'name');
        $rows = [];
        foreach ($this->result['rows'] as $cells) {
            $values = [];
            foreach ($cells as $cell) {
                $values[] = $cell['type'] === 'integer' ? (int)$cell['value'] : ($cell['value'] ?? null);
            }
            $rows[] = array_combine($names, $values);
        }
        return $rows;
    }
    public function fetch(): array|false { return $this->fetchAll()[0] ?? false; }
    public function fetchColumn(): mixed {
        $row = $this->fetch();
        return $row === false ? false : reset($row);
    }
    public function rowCount(): int { return $this->result['affected_row_count']; }
}
