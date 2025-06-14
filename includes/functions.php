<?php
function getRoleBadge($roleId) {
    $roles = [
        1 => ['name' => 'Super Admin', 'class' => 'badge bg-danger'],
        2 => ['name' => 'Admin', 'class' => 'badge bg-warning text-dark'],
        3 => ['name' => 'Professeur', 'class' => 'badge bg-info'],
        4 => ['name' => 'Étudiant', 'class' => 'badge bg-success']
    ];
    
    return $roles[$roleId] ?? ['name' => 'Inconnu', 'class' => 'badge bg-secondary'];
}