<?php

declare(strict_types=1);

namespace PHPCoord;

/**
 * Abstract class representing a Tranverse Mercator Projection.
 * @author Doug Wright
 */
abstract class TransverseMercator
{
    /**
     * X.
     * @var int
     */
    protected $x;

    /**
     * Y.
     * @var int
     */
    protected $y;

    /**
     * H.
     * @var int
     */
    protected $h;

    /**
     * Reference ellipsoid used in this datum.
     * @var RefEll
     */
    protected $refEll;

    /**
     * Cartesian constructor.
     * @param int    $x
     * @param int    $y
     * @param int    $h
     * @param RefEll $refEll
     */
    public function __construct(int $x, int $y, int $h, RefEll $refEll)
    {
        $this->x = $x;
        $this->y = $y;
        $this->h = $h;
        $this->refEll = $refEll;
    }

    /**
     * String version of coordinate.
     * @return string
     */
    public function __toString(): string
    {
        return "({$this->x}, {$this->y}, {$this->h})";
    }

    /**
     * @return int
     */
    public function getX(): int
    {
        return $this->x;
    }

    /**
     * @return int
     */
    public function getY(): int
    {
        return $this->y;
    }

    /**
     * @return int
     */
    public function getH(): int
    {
        return $this->h;
    }

    /**
     * Reference ellipsoid used by this projection.
     * @return RefEll
     */
    abstract public function getReferenceEllipsoid(): RefEll;

    /**
     * Scale factor at central meridian.
     * @return float
     */
    abstract public function getScaleFactor(): float;

    /**
     * Northing of true origin.
     * @return int
     */
    abstract public function getOriginNorthing(): int;

    /**
     * Easting of true origin.
     * @return int
     */
    abstract public function getOriginEasting(): int;

    /**
     * Latitude of true origin.
     * @return float
     */
    abstract public function getOriginLatitude(): float;

    /**
     * Longitude of true origin.
     * @return float
     */
    abstract public function getOriginLongitude(): float;

    /**
     * Convert this grid reference into a latitude and longitude
     * Formula for transformation is taken from OS document
     * "A Guide to Coordinate Systems in Great Britain".
     *
     * @param  float  $N       map coordinate (northing) of point to convert
     * @param  float  $E       map coordinate (easting) of point to convert
     * @param  float  $N0      map coordinate (northing) of true origin
     * @param  float  $E0      map coordinate (easting) of true origin
     * @param  float  $phi0    map coordinate (latitude) of true origin
     * @param  float  $lambda0 map coordinate (longitude) of true origin and central meridian
     * @return LatLng
     */
    public function convertToLatitudeLongitude(float $N, float $E, float $N0, float $E0, float $phi0, float $lambda0): LatLng
    {
        $phi0 = deg2rad($phi0);
        $lambda0 = deg2rad($lambda0);

        $refEll = $this->getReferenceEllipsoid();
        $F0 = $this->getScaleFactor();

        $a = $refEll->getMaj();
        $b = $refEll->getMin();
        $eSquared = $refEll->getEcc();
        $n = ($a - $b) / ($a + $b);
        $phiPrime = (($N - $N0) / ($a * $F0)) + $phi0;

        do {
            $M =
                ($b * $F0)
                * (((1 + $n + ((5 / 4) * $n * $n) + ((5 / 4) * $n * $n * $n))
                        * ($phiPrime - $phi0))
                    - (((3 * $n) + (3 * $n * $n) + ((21 / 8) * $n * $n * $n))
                        * sin($phiPrime - $phi0)
                        * cos($phiPrime + $phi0))
                    + ((((15 / 8) * $n * $n) + ((15 / 8) * $n * $n * $n))
                        * sin(2 * ($phiPrime - $phi0))
                        * cos(2 * ($phiPrime + $phi0)))
                    - (((35 / 24) * $n * $n * $n)
                        * sin(3 * ($phiPrime - $phi0))
                        * cos(3 * ($phiPrime + $phi0))));
            $phiPrime += ($N - $N0 - $M) / ($a * $F0);
        } while (($N - $N0 - $M) >= 0.00001);
        $v = $a * $F0 * ((1 - $eSquared * (sin($phiPrime) ** 2)) ** -0.5);
        $rho =
            $a
            * $F0
            * (1 - $eSquared)
            * ((1 - $eSquared * (sin($phiPrime) ** 2)) ** -1.5);
        $etaSquared = ($v / $rho) - 1;
        $VII = tan($phiPrime) / (2 * $rho * $v);
        $VIII =
            (tan($phiPrime) / (24 * $rho * ($v ** 3)))
            * (5
                + (3 * (tan($phiPrime) ** 2))
                + $etaSquared
                - (9 * (tan($phiPrime) ** 2) * $etaSquared));
        $IX =
            (tan($phiPrime) / (720 * $rho * ($v ** 5)))
            * (61
                + (90 * (tan($phiPrime) ** 2))
                + (45 * (tan($phiPrime) ** 2) * (tan($phiPrime) ** 2)));
        $X = (1 / cos($phiPrime)) / $v;
        $XI =
            ((1 / cos($phiPrime)) / (6 * $v * $v * $v))
            * (($v / $rho) + (2 * (tan($phiPrime) ** 2)));
        $XII =
            ((1 / cos($phiPrime)) / (120 * ($v ** 5)))
            * (5
                + (28 * (tan($phiPrime) ** 2))
                + (24 * (tan($phiPrime) ** 4)));
        $XIIA =
            ((1 / cos($phiPrime)) / (5040 * ($v ** 7)))
            * (61
                + (662 * (tan($phiPrime) ** 2))
                + (1320 * (tan($phiPrime) ** 4))
                + (720
                    * (tan($phiPrime) ** 6)));
        $phi =
            $phiPrime
            - ($VII * (($E - $E0) ** 2))
            + ($VIII * (($E - $E0) ** 4))
            - ($IX * (($E - $E0) ** 6));
        $lambda =
            $lambda0
            + ($X * ($E - $E0))
            - ($XI * (($E - $E0) ** 3))
            + ($XII * (($E - $E0) ** 5))
            - ($XIIA * (($E - $E0) ** 7));

        return new LatLng(rad2deg($phi), rad2deg($lambda), 0, $refEll);
    }

    /**
     * Calculate the surface distance between this object and the one
     * passed in as a parameter.
     *
     * @param  self $to object to measure the distance to
     * @return int
     */
    public function distance(self $to): int
    {
        if ($this->refEll != $to->refEll) {
            throw new \RuntimeException('Source and destination co-ordinates are not using the same ellipsoid');
        }

        //Because this is a 2D grid, we can use simple Pythagoras
        $distanceX = $to->getX() - $this->getX();
        $distanceY = $to->getY() - $this->getY();

        return (int) round((($distanceX ** 2) + ($distanceY ** 2)) ** 0.5);
    }
}
