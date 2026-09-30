<?php
namespace erpsoftsas;

/*
 * Paginacion en el SERVIDOR para los listados de la Alcaldia (Contribuyentes y
 * Establecimientos del municipio). Pedido del cliente (2026-09-29): ver de a 5,
 * 10, 20, 50 o 100 con sus paginas, y cuantos hay en total. Antes se traian los
 * 20 mas recientes y se pedia "escribir para buscar entre todos".
 *
 * La consulta llega partida: $desde es "FROM ... [WHERE ...]" con sus '?' en
 * $parametros, $campos la lista de columnas y $orden el ORDER BY (obligatorio
 * para OFFSET/FETCH). Se cuentan los que cumplen el filtro y la pagina se pide
 * con OFFSET ... FETCH NEXT (SQL Server 2012 o superior).
 */
class Paginacion
{
    const TAMANOS = [5, 10, 20, 50, 100];
    const TAMANO_POR_DEFECTO = 10;

    /** Pagina y tamaño pedidos, saneados. */
    public static function leerPedido(array $post)
    {
        $porPagina = (int) ($post['porPagina'] ?? self::TAMANO_POR_DEFECTO);
        if (!in_array($porPagina, self::TAMANOS, true)) { $porPagina = self::TAMANO_POR_DEFECTO; }
        $pagina = max(1, (int) ($post['pagina'] ?? 1));
        return [$pagina, $porPagina];
    }

    /**
     * @return array filas, total (con el filtro), pagina, porPagina, paginas
     */
    public static function consultar($con, $campos, $desde, array $parametros, $orden, $pagina, $porPagina)
    {
        $fila  = $con->obnerFila($con->consultar("SELECT COUNT(*) AS n $desde", $parametros));
        $total = (int) ($fila['n'] ?? 0);

        $paginas = max(1, (int) ceil($total / $porPagina));
        // Pedir una pagina que ya no existe (se borro algo, o cambio el filtro)
        // lleva a la ultima que si tiene filas, no a una tabla vacia.
        $pagina = min(max(1, (int) $pagina), $paginas);

        $stmt = $con->consultar(
            "SELECT $campos $desde ORDER BY $orden OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
            array_merge($parametros, [($pagina - 1) * $porPagina, $porPagina])
        );
        $filas = [];
        while ($f = $con->obnerFila($stmt)) { $filas[] = $f; }

        return [
            'filas'     => $filas,
            'total'     => $total,
            'pagina'    => $pagina,
            'porPagina' => $porPagina,
            'paginas'   => $paginas,
            // Compatibilidad con las pantallas anteriores (en caché del navegador).
            'hayMas'    => $pagina < $paginas,
        ];
    }

    /** Cuantos hay en la tabla, sin filtro: "N contribuyentes registrados". */
    public static function contar($con, $desde)
    {
        $fila = $con->obnerFila($con->consultar("SELECT COUNT(*) AS n $desde"));
        return (int) ($fila['n'] ?? 0);
    }
}
