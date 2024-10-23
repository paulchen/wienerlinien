# JSON-API

Eigentlich gedacht für die Verwendung durch die Kartenansicht unter `/map` existiert eine einfache JSON-API zum Abrufen bestimmter Daten.
Die erste wesentliche Erweiterung hat die API für den [Abfahrsmonitor](https://github.com/paulchen/departure-monitor) erhalten.

## Stationsdaten

**URL**: [https://rueckgr.at/wienerlinien/map/stations.php](https://rueckgr.at/wienerlinien/map/stations.php)

```
{
    "line_types": [
        {
            "id": 1,
            "color": "ff0000"
        }
    ],
    "lines": [
        {
            "id": 1,
            "name": "U2",
            "type": 4,
            "color": "a065aa"
        } 
    ],
    "stations": [
        {
            "id": 103014,
            "name": "Schottentor",
            "lat": 48.2139,
            "lon": 16.3623,
            "line_list": [1, 9, 12, 14, 24, 25, 26, 27, 28, 29, 30, 149, 158, 211, 216, 217, 218, 222, 225, 632]
        } 
    ]
}
```

- `line_types` ist grundsätzlich eine Liste von Zuordnungen von IDs (`type_id`) zu Farben; an der Type ist grundsätzlich auch unterscheidbar, ob eine Linie eine Straßenbahn, eine U-Bahn, ein Bus etc. ist.
- `lines` ist eine Liste aller bekannten Linien; `type` verweist auf eine `type_id` (siehe oben); `color` ist optional (wenn sie nicht vorhanden ist, gilt die Farbe der Type).
- `stations` sind die bekannten Stationen. `lat` und `lon` sind die WGS84-Koordinaten, `line_list` sind die IDs der Linien, die da fahren (siehe oben). Diese Liste enthält z.B. `1`, d.h. am Schottentor fährt die U2.

## Bahnsteigdaten

Mit der ID einer Station können dann die Bahnsteige abgerufen werden.

**URL**: [https://rueckgr.at/wienerlinien/map/platforms.php?id=103014](https://rueckgr.at/wienerlinien/map/platforms.php?id=103014)

```
{
    "name": "Schottentor",
    "platforms": [
        {
            "rbl": 4203,
            "platform": "1",
            "line_names": [
                "U2"
            ],
            "line_ids": [
                "1"
            ]
        },
        {
            "rbl": 4212,
            "platform": "2",
            "line_names": [
                "U2"
            ],
            "line_ids": [
                "1"
            ]
        }
    ]
} 
```

## Live-Abfahrsdaten

Mit der RBL-Nummer können dann die Live-Abfahrtszeiten abgefragt werden.

**URL**: [https://rueckgr.at/wienerlinien/map/rbls.php?ids=4203,4212](https://rueckgr.at/wienerlinien/map/rbls.php?ids=4203,4212)

```
{
    "4212": [
        {
            "line": "U2",
            "line_id": 1,
            "towards": "SEESTADT",
            "towards_id": 102800,
            "barrier_free": true,
            "folding_ramp": true,
            "realtime_supported": true,
            "time": 2,
            "time_planned": "2024-10-23T23:15:34.000+0200",
            "time_real": "2024-10-23T23:15:34.000+0200",
            "gate": "2"
        },
        {
            "line": "U2",
            "line_id": 1,
            "towards": "SEESTADT",
            "towards_id": 102800,
            "barrier_free": true,
            "folding_ramp": false,
            "realtime_supported": true,
            "time": 10,
            "time_planned": "2024-10-23T23:23:34.000+0200",
            "time_real": "2024-10-23T23:23:34.000+0200",
            "gate": "2"
        }
    ],
    "4203": [
        {
            "line": "U2",
            "line_id": 1,
            "towards": "Aspernstraße",
            "towards_id": 102444,
            "barrier_free": false,
            "folding_ramp": false,
            "realtime_supported": true,
            "time": 6
        },
        {
            "line": "U2",
            "line_id": 1,
            "towards": "ASPERNSTRASSE",
            "towards_id": null,
            "barrier_free": true,
            "folding_ramp": true,
            "realtime_supported": true,
            "time": 7
        }
    ]
}
```

`towards_id` ist wieder die ID einer Station. 

## Störungsmeldungen


Störungsmeldungen kann man mit der ID einer Station abrufen.

**URL**: [https://rueckgr.at/wienerlinien/map/disruptions.php?id=103014](https://rueckgr.at/wienerlinien/map/disruptions.php?id=103014)

```
{
    "lines": {
        "1": [
            {
                "line": "1",
                "title": "1, 62, Badner Bahn: Teilstreckensperre",
                "description": "Wegen der Umgestaltung der Wiedner Hauptstraße fahren die Linien 1 und 62 bis November umgeleitet. Die Linie 1 fährt zwischen Oper, Karlsplatz und Kliebergasse eine Umleitung über Hauptbahnhof. Die Linie 62 fährt nur zwischen Lainz, Wolkersbergenstraße und Bhf. Meidling, Dörfelstraße. Die Badner Bahn fährt nur zwischen Baden und Kliebergasse und wird weiter nach Quartier Belvedere umgeleitet. Weichen Sie auf die U1, U4, U6, 13A, 59A und die S-Bahn aus.",
                "start_time": "2024-04-02 03:00:00",
                "end_time": "2024-11-30 22:59:00"
            }
        ]
    },
    "rbls
    ": []
}
```

Die Störungsmeldungen gehören immer zu einer Linie (da ist der Key der Name (!) der Linie) oder zu einem Bahnsteig (da ist der Key die RBL-ID).

## Geographische Daten

Weiters gibt es geographische Daten zu Strecke und Haltestellen von Linien; wieder über die IDs der Linien.

**URL**: [https://rueckgr.at/wienerlinien/map/json.php?lines=1](https://rueckgr.at/wienerlinien/map/json.php?lines=1)

```
[
    {
        "line": "1",
        "name": "U2",
        "segments": [
            [
                [
                    "48.21503376",
                    "16.36286775"
                ],
                [
                    "48.21503876",
                    "16.36287885"
                ]
            ]
        ],
        "color": "a065aa",
        "line_thickness": 4,
        "stations": [
            {
                "id": 102800,
                "name": "Seestadt",
                "lat": "48.226091",
                "lon": "16.508502"
            },
            {
                "id": 103159,
                "name": "Aspern Nord",
                "lat": "48.234606",
                "lon": "16.504729"
            }
        ]
    }
]
```

