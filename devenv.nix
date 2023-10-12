{ pkgs, lib, config, ... }:

{
  env.DATABASE_URL = lib.mkDefault "mysql://root@localhost:3306/shopware";
  env.OPENSEARCH_URL = lib.mkDefault "http://localhost:9200";

  services.postgres.enable = lib.mkDefault true;
  services.mongodb.enable = lib.mkDefault true;
  services.opensearch.enable = true;
  services.redis.enable = lib.mkDefault true;

  languages.php = {
    enable = lib.mkDefault true;
    version = lib.mkDefault "8.1";
    extensions = [ "mongodb" ];
  };

  services.mysql = {
    enable = true;
    package = pkgs.mysql80;
    initialDatabases = lib.mkDefault [{ name = "shopware"; }];
    ensureUsers = lib.mkDefault [
      {
        name = "shopware";
        password = "shopware";
        ensurePermissions = {
          "shopware.*" = "ALL PRIVILEGES";
        };
      }
    ];
    settings = {
      mysqld = {
        log_bin_trust_function_creators = 1;
        sql_mode = "STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION";
      };
    };
  };
}