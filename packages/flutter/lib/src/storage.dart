abstract interface class MkapSecureStorage {
  Future<void> write(String key, String exportJson);
  Future<String?> read(String key);
  Future<void> delete(String key);
}

class InMemoryMkapStorage implements MkapSecureStorage {
  final Map<String, String> _values = <String, String>{};

  @override
  Future<void> write(String key, String exportJson) async {
    _values[key] = exportJson;
  }

  @override
  Future<String?> read(String key) async => _values[key];

  @override
  Future<void> delete(String key) async {
    _values.remove(key);
  }
}
