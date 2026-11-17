<?php

namespace App\Services\ZooDeck;

use App\Exceptions\DuelException;
use App\Models\Ticket;
use App\Models\User;
use App\Models\ZooZone;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reglas de visita para jugar ZooDeck: estar dentro del zoológico
 * y tener un boleto pagado hoy.
 *
 * El mapa de ZooDeck, validate-capture y los duelos usan esta misma clase,
 * así que lo que el mapa ofrece es lo que el servidor acepta.
 */
class VisitValidator
{
    public const REASON_LOCATION_REQUIRED = 'location_required';

    public const REASON_OUTSIDE_ZOO = 'outside_zoo';

    public const REASON_NO_TICKET = 'no_ticket';

    /** @var Collection<int, ZooZone>|null */
    private ?Collection $zones = null;

    public function enforced(): bool
    {
        return (bool) config('app.zoodeck_enforce_validations');
    }

    /**
     * Boleto activo de una orden pagada hoy.
     *
     * Las órdenes no tienen fecha de visita ni hay registro en la entrada
     * (`used_at` nunca se llena), así que el día de pago es el día de visita.
     */
    public function hasTicketForToday(User $user): bool
    {
        return Ticket::query()
            ->where('status', 'active')
            ->whereHas('order', function ($query) use ($user) {
                $query
                    ->where('user_id', $user->id)
                    ->where('status', 'paid')
                    ->whereDate('paid_at', today());
            })
            ->exists();
    }

    /**
     * Dentro de una zona activa, o a no más de la precisión del GPS de su borde.
     *
     * La precisión (`accuracy`, en metros, la que reporta el teléfono) se acota con
     * `app.zoodeck_location_tolerance_meters`: una lectura mala junto a la cerca no
     * debe dejar fuera a quien está en un recinto del borde, pero una precisión enorme
     * tampoco puede aceptar a cualquiera.
     */
    public function isInsideZoo(float $latitude, float $longitude, ?float $accuracy = null): bool
    {
        $zones = $this->activeZones();

        if ($zones->contains(fn (ZooZone $zone) => $this->pointInGeometry($latitude, $longitude, $zone->geometry))) {
            return true;
        }

        $tolerance = $this->tolerance($accuracy);

        return $tolerance > 0 && $zones->contains(
            fn (ZooZone $zone) => $this->distanceToGeometry($latitude, $longitude, $zone->geometry) <= $tolerance
        );
    }

    /**
     * Metros de tolerancia para una precisión dada: nunca más que el tope configurado.
     */
    public function tolerance(?float $accuracy): float
    {
        if ($accuracy === null || $accuracy <= 0) {
            return 0.0;
        }

        return min($accuracy, max(0, (float) config('app.zoodeck_location_tolerance_meters')));
    }

    /**
     * Motivo por el que el usuario no puede retar, o null si puede.
     *
     * Se evalúa aunque la validación esté apagada, para mostrarlo como aviso.
     */
    public function reason(User $user, ?float $latitude, ?float $longitude, ?float $accuracy = null): ?string
    {
        if ($latitude === null || $longitude === null) {
            return self::REASON_LOCATION_REQUIRED;
        }

        if (! $this->isInsideZoo($latitude, $longitude, $accuracy)) {
            return self::REASON_OUTSIDE_ZOO;
        }

        if (! $this->hasTicketForToday($user)) {
            return self::REASON_NO_TICKET;
        }

        return null;
    }

    /**
     * Estado de la visita para el mapa de ZooDeck, con la misma prioridad que reason().
     *
     * @return array{enforced: bool, ticket: bool, inside_zoo: bool|null, can_duel: bool, reason: string|null}
     */
    public function status(User $user, ?float $latitude, ?float $longitude, ?float $accuracy = null): array
    {
        $hasLocation = $latitude !== null && $longitude !== null;
        $insideZoo = $hasLocation ? $this->isInsideZoo($latitude, $longitude, $accuracy) : null;
        $ticket = $this->hasTicketForToday($user);

        $reason = match (true) {
            ! $hasLocation => self::REASON_LOCATION_REQUIRED,
            ! $insideZoo => self::REASON_OUTSIDE_ZOO,
            ! $ticket => self::REASON_NO_TICKET,
            default => null,
        };

        $enforced = $this->enforced();

        return [
            'enforced' => $enforced,
            'ticket' => $ticket,
            'inside_zoo' => $insideZoo,
            'can_duel' => ! $enforced || $reason === null,
            'reason' => $reason,
        ];
    }

    /**
     * Detener el duelo si la validación está activa y no se cumple.
     */
    public function ensureCanDuel(User $user, ?float $latitude, ?float $longitude, ?float $accuracy = null): void
    {
        if (! $this->enforced()) {
            return;
        }

        match ($this->reason($user, $latitude, $longitude, $accuracy)) {
            self::REASON_LOCATION_REQUIRED => throw DuelException::locationRequired(),
            self::REASON_OUTSIDE_ZOO => throw DuelException::outsideZoo(),
            self::REASON_NO_TICKET => throw DuelException::noTicketToday(),
            default => null,
        };
    }

    /**
     * @return Collection<int, ZooZone>
     */
    private function activeZones(): Collection
    {
        return $this->zones ??= ZooZone::query()
            ->where('is_active', true)
            ->get(['id', 'geometry']);
    }

    /**
     * Polygon o MultiPolygon de GeoJSON, con coordenadas [longitud, latitud].
     * Un punto dentro de un hueco del polígono queda fuera.
     */
    private function pointInGeometry(float $latitude, float $longitude, mixed $geometry): bool
    {
        if (is_string($geometry)) {
            $geometry = json_decode($geometry, true);
        }

        if (! is_array($geometry)) {
            return false;
        }

        $coordinates = $geometry['coordinates'] ?? [];

        $polygons = match ($geometry['type'] ?? null) {
            'Polygon' => [$coordinates],
            'MultiPolygon' => $coordinates,
            default => [],
        };

        foreach ($polygons as $polygon) {
            if (! $this->pointInRing($longitude, $latitude, $polygon[0] ?? [])) {
                continue;
            }

            $insideHole = false;

            foreach (array_slice($polygon, 1) as $hole) {
                if ($this->pointInRing($longitude, $latitude, $hole)) {
                    $insideHole = true;
                    break;
                }
            }

            if (! $insideHole) {
                return true;
            }
        }

        return false;
    }

    /**
     * Distancia en metros del punto al borde más cercano de la geometría (anillos
     * exteriores y huecos). Para un punto fuera de la zona es su distancia a ella.
     */
    private function distanceToGeometry(float $latitude, float $longitude, mixed $geometry): float
    {
        if (is_string($geometry)) {
            $geometry = json_decode($geometry, true);
        }

        if (! is_array($geometry)) {
            return INF;
        }

        $coordinates = $geometry['coordinates'] ?? [];

        $polygons = match ($geometry['type'] ?? null) {
            'Polygon' => [$coordinates],
            'MultiPolygon' => $coordinates,
            default => [],
        };

        $nearest = INF;

        foreach ($polygons as $polygon) {
            foreach ($polygon as $ring) {
                $nearest = min($nearest, $this->distanceToRing($latitude, $longitude, $ring));
            }
        }

        return $nearest;
    }

    /**
     * Proyección equirectangular centrada en el punto: a la escala de un zoológico el
     * error es de centímetros y evita trigonometría esférica por segmento.
     *
     * @param  array<int, array<int, float|int|string>>  $ring
     */
    private function distanceToRing(float $latitude, float $longitude, array $ring): float
    {
        $metersPerDegree = M_PI * 6371000 / 180;
        $metersPerLongitude = $metersPerDegree * cos(deg2rad($latitude));

        $points = array_map(fn (array $point) => [
            ((float) $point[0] - $longitude) * $metersPerLongitude,
            ((float) $point[1] - $latitude) * $metersPerDegree,
        ], $ring);

        $nearest = INF;

        for ($i = 1, $count = count($points); $i < $count; $i++) {
            [$ax, $ay] = $points[$i - 1];
            [$bx, $by] = $points[$i];

            $dx = $bx - $ax;
            $dy = $by - $ay;
            $lengthSquared = $dx * $dx + $dy * $dy;

            // Proyección del origen (el punto) sobre el segmento, acotada a sus extremos.
            $t = $lengthSquared > 0
                ? max(0, min(1, -($ax * $dx + $ay * $dy) / $lengthSquared))
                : 0;

            $nearest = min($nearest, hypot($ax + $t * $dx, $ay + $t * $dy));
        }

        return $nearest;
    }

    /**
     * Ray casting sobre un anillo de GeoJSON.
     *
     * @param  array<int, array<int, float|int|string>>  $ring
     */
    private function pointInRing(float $longitude, float $latitude, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        if ($count < 3) {
            return false;
        }

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = (float) $ring[$i][0];
            $yi = (float) $ring[$i][1];
            $xj = (float) $ring[$j][0];
            $yj = (float) $ring[$j][1];

            $intersects = (($yi > $latitude) !== ($yj > $latitude))
                && ($longitude < ($xj - $xi) * ($latitude - $yi) / (($yj - $yi) ?: 0.000000000001) + $xi);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
