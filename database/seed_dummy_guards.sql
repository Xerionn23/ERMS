USE erms;

SET @license_type_id := (SELECT id FROM requirement_types WHERE code = 'SECURITY_LICENSE' LIMIT 1);

INSERT INTO guards (
    guard_no,
    last_name,
    first_name,
    middle_name,
    suffix,
    birthdate,
    age,
    agency,
    full_name,
    contact_no,
    status
)
SELECT
    CONCAT('JG-D', LPAD(nums.n, 6, '0')) AS guard_no,
    ELT(1 + MOD(nums.n - 1, 30),
        'Dela Cruz','Reyes','Santos','Garcia','Mendoza','Torres','Ramos','Flores','Gonzales','Aquino',
        'Castillo','Navarro','Velasco','Dominguez','Herrera','Bautista','Pascual','Salazar','Villanueva','Cabrera',
        'Padilla','Mercado','Silva','Lopez','Rivera','Diaz','Alvarez','Morales','Pineda','Delos Santos'
    ) AS last_name,
    ELT(1 + MOD(nums.n - 1, 30),
        'Juan','Jose','Miguel','Mark','Paolo','Carlo','Joshua','Christian','Anthony','Gabriel',
        'Ramon','Francis','Angelo','Nathaniel','Ian','Jerome','Bryan','Ken','Daniel','Ryan',
        'Patrick','Jasper','Vincent','Noel','Arnel','Rolando','Eduardo','Marco','John','Emmanuel'
    ) AS first_name,
    ELT(1 + MOD(nums.n - 1, 12),
        'Antonio','Bernardo','Cruz','Domingo','Enrique','Francisco','Gerardo','Hernandez','Ignacio','Lorenzo','Manuel','Santiago'
    ) AS middle_name,
    CASE
        WHEN MOD(nums.n, 28) = 0 THEN 'Jr.'
        WHEN MOD(nums.n, 45) = 0 THEN 'Sr.'
        WHEN MOD(nums.n, 60) = 0 THEN 'III'
        ELSE NULL
    END AS suffix,
    DATE_SUB(CURDATE(), INTERVAL (22 * 365 + (MOD(nums.n, 3650))) DAY) AS birthdate,
    (22 + MOD(nums.n, 24)) AS age,
    ELT(1 + MOD(nums.n - 1, 5),
        'Jubecer Security Services','Aegis Shield Agency','Guardian Watch Corp.','Blue Sentinel Agency','Prime Guard Solutions'
    ) AS agency,
    CONCAT(
        ELT(1 + MOD(nums.n - 1, 30),
            'Dela Cruz','Reyes','Santos','Garcia','Mendoza','Torres','Ramos','Flores','Gonzales','Aquino',
            'Castillo','Navarro','Velasco','Dominguez','Herrera','Bautista','Pascual','Salazar','Villanueva','Cabrera',
            'Padilla','Mercado','Silva','Lopez','Rivera','Diaz','Alvarez','Morales','Pineda','Delos Santos'
        ),
        ', ',
        ELT(1 + MOD(nums.n - 1, 30),
            'Juan','Jose','Miguel','Mark','Paolo','Carlo','Joshua','Christian','Anthony','Gabriel',
            'Ramon','Francis','Angelo','Nathaniel','Ian','Jerome','Bryan','Ken','Daniel','Ryan',
            'Patrick','Jasper','Vincent','Noel','Arnel','Rolando','Eduardo','Marco','John','Emmanuel'
        ),
        ' ',
        LEFT(
            ELT(1 + MOD(nums.n - 1, 12),
                'Antonio','Bernardo','Cruz','Domingo','Enrique','Francisco','Gerardo','Hernandez','Ignacio','Lorenzo','Manuel','Santiago'
            ),
            1
        ),
        '.',
        CASE
            WHEN MOD(nums.n, 28) = 0 THEN ' Jr.'
            WHEN MOD(nums.n, 45) = 0 THEN ' Sr.'
            WHEN MOD(nums.n, 60) = 0 THEN ' III'
            ELSE ''
        END
    ) AS full_name,
    CONCAT('09', LPAD(100000000 + MOD(nums.n * 7919, 900000000), 9, '0')) AS contact_no,
    'active' AS status
FROM (
    SELECT (d0.n + (d1.n * 10) + (d2.n * 100)) + 1 AS n
    FROM
        (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) d0
        CROSS JOIN (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) d1
        CROSS JOIN (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) d2
) nums
WHERE nums.n <= 150
  AND NOT EXISTS (
      SELECT 1 FROM guards g WHERE g.guard_no = CONCAT('JG-D', LPAD(nums.n, 6, '0'))
  );

INSERT INTO guard_requirements (
    guard_id,
    requirement_type_id,
    document_no,
    issued_date,
    expiry_date
)
SELECT
    g.id AS guard_id,
    @license_type_id AS requirement_type_id,
    CONCAT('LIC-', g.guard_no) AS document_no,
    DATE_SUB(CURDATE(), INTERVAL (g.id % 365) DAY) AS issued_date,
    CASE
        WHEN (g.id % 10) < 2 THEN DATE_SUB(CURDATE(), INTERVAL (1 + (g.id % 120)) DAY)
        WHEN (g.id % 10) < 5 THEN DATE_ADD(CURDATE(), INTERVAL (1 + (g.id % 170)) DAY)
        ELSE DATE_ADD(CURDATE(), INTERVAL (220 + (g.id % 600)) DAY)
    END AS expiry_date,
FROM guards g
WHERE g.guard_no LIKE 'JG-D%'
  AND @license_type_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM guard_requirements gr
      WHERE gr.guard_id = g.id
        AND gr.requirement_type_id = @license_type_id
  );
