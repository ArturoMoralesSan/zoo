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

    public function isInsideZoo(float $latitude, float $longitude): bool
    {
        return $this->activeZones()->contains(
            fn (ZooZone $zone) => $this->pointInGeometry($latitude, $longitude, $zone->geometry)
        );
    }

    /**
     * Motivo por el que el usuario no puede retar, o null si puede.
     *
     * Se evalúa aunque la validación esté apagada, para mostrarlo como aviso.
     */
    public function reason(User $user, ?float $latitude, ?float $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return self::REASON_LOCATION_REQUIRED;
        }

        if (! $this->isInsideZoo($latitude, $longitude)) {
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
    public function status(User $user, ?float $latitude, ?float $longitude): array
    {
        $hasLocation = $latitude !== null && $longitude !== null;
        $insideZoo = $hasLocation ? $this->isInsideZoo($latitude, $longitude) : null;
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
    public function ensureCanDuel(User $user, ?float $latitude, ?float $longitude): void
    {
        if (! $this->enforced()) {
            return;
        }

        match ($this->reason($user, $latitude, $longitude)) {
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
