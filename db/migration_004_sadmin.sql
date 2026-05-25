-- Migration 004 — adiciona role 'sadmin' (super admin)
-- E promove o admin@diteads.com para sadmin.

ALTER TABLE usuarios
    MODIFY role ENUM('sadmin','admin','funcionario','cliente') NOT NULL DEFAULT 'funcionario';

UPDATE usuarios SET role = 'sadmin' WHERE email = 'admin@diteads.com';
