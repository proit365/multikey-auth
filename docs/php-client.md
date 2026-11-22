# PHP Client

Use `packages/client-php`:

```php
$pool = (new ExportLoader())->fromJson($exportJson);
$client = new PhpMultiKeyClient(
    new ClientConfig('https://api.example.test', 'CLIENT_001', 1, 6, 'abcdefghijklmnopqrstuvwxyz0123456789'),
    $pool,
    $transport
);
$response = $client->request('POST', '/api/orders', '{"status":"pending"}');
```

The transport must preserve the method, target, exact body bytes, and headers.
It must not follow redirects. API 401 responses are returned unchanged.
