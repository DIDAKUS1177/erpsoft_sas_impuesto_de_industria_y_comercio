<?php
namespace ConexionMysqlUsuariosSqlServerPaz;

class ConexionSQLServerPaz {
    private static $instance = null;
    private $_link;

    private function __construct() {
        $serverName = "178.156.143.97";
        $connectionOptions = [
            "Database" => "erpsofts_paipa",
            "Uid" => "erpsoftsas_predial",
            "PWD" => "a&4gA564o_2024.",
            "CharacterSet" => "UTF-8"
        ];

        $this->_link = sqlsrv_connect($serverName, $connectionOptions);

        if (!$this->_link) {
            throw new \Exception("Error de conexión: " . print_r(sqlsrv_errors(), true));
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new ConexionSQLServerPaz();
        }
        return self::$instance;
    }

    public function consultarConParametros($query, $params = []) {
        $stmt = sqlsrv_query($this->_link, $query, $params);
        if ($stmt === false) {
            throw new \Exception("Error ejecutando consulta: " . print_r(sqlsrv_errors(), true));
        }
        return $stmt;
    }

    public function obtenerDatos($stmt) {
        $data = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
        return $data;
    }
}
