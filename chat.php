<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

checkAuth(); // Seuls les utilisateurs connectés peuvent accéder au chat

// Récupérer l'utilisateur courant
$currentUser = currentUser();
$userId = $_SESSION['user_id'];

// Récupérer les conversations
try {
    // Conversations personnelles
    $privateChats = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.photo, u.role_id, 
           (SELECT COUNT(*) FROM chat_messages 
            WHERE recipient_id = u.id AND sender_id = ? AND is_read = FALSE) AS unread
    FROM users u
    WHERE u.id != ? AND u.id IN (
        SELECT DISTINCT IF(sender_id = ?, recipient_id, sender_id)
        FROM chat_messages
        WHERE sender_id = ? OR recipient_id = ?
    )
    ORDER BY unread DESC, u.last_name
");
$privateChats->execute([$userId, $userId, $userId, $userId, $userId]);

    // Conversations de groupe (si implémenté)
    $groupChats = $pdo->prepare("
        SELECT group_id, group_name, 
               (SELECT COUNT(*) FROM chat_messages 
                WHERE group_id = c.group_id 
                AND is_read = FALSE 
                AND sender_id != ?) AS unread
        FROM chat_groups c
        JOIN group_members gm ON c.group_id = gm.group_id
        WHERE gm.user_id = ?
        ORDER BY unread DESC, group_name
    ");
    $groupChats->execute([$userId, $userId]); // Décommenter si groupes implémentés
} catch (PDOException $e) {
    logEvent("Erreur chat: " . $e->getMessage());
    $error = "Erreur lors du chargement des conversations";
}

$pageTitle = "Messagerie Instantanée - CFP-CMD";
include_once 'includes/header2.php';
?>

<div class="chat-container">
    <!-- Sidebar des conversations -->
    <div class="chat-sidebar">
        <div class="chat-header">
            <h3><i class="fas fa-comments"></i> Live Chat</h3>
            <button class="btn btn-sm btn-primary" id="newChatBtn">
                <i class="fas fa-plus"></i> Nouveau
            </button>
        </div>
        
        <!-- Recherche -->
        <div class="chat-search">
            <input type="text" id="chatSearch" placeholder="Rechercher une conversation...">
            <button class="btn btn-sm"><i class="fas fa-search"></i></button>
        </div>
        
        <!-- Liste des conversations -->
        <div class="chat-conversations">
            <ul class="nav nav-pills flex-column" id="conversationList">
                <!-- Conversations privées -->
                <?php foreach ($privateChats->fetchAll() as $chat): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link chat-link" data-type="private" data-id="<?= $chat['id'] ?>">
                            <div class="d-flex align-items-center">
                                <img src="assets/images/users/<?= htmlspecialchars($chat['photo']) ?>" 
                                     class="rounded-circle me-2" width="40" height="40" alt="Photo profil">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= htmlspecialchars($chat['first_name'] . ' ' . $chat['last_name']) ?></strong><small class="text-muted">
    <?php $role = getRoleBadge($chat['role_id']); ?>
    <span class="<?= $role['class'] ?>"><?= $role['name'] ?></span>
</small>
                                    </div>
                                    <small class="text-muted last-message">Chargement...</small>
                                </div>
                                <?php if ($chat['unread'] > 0): ?>
                                    <span class="badge bg-danger rounded-pill ms-2"><?= $chat['unread'] ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
                
                <!-- Conversations de groupe (optionnel) -->
                <!--
                <?php foreach ($groupChats->fetchAll() as $group): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link chat-link" data-type="group" data-id="<?= $group['group_id'] ?>">
                            <div class="d-flex align-items-center">
                                <div class="group-avatar rounded-circle me-2 bg-secondary text-white d-flex align-items-center justify-content-center" 
                                     style="width: 40px; height: 40px;">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <strong><?= htmlspecialchars($group['group_name']) ?></strong>
                                    <small class="text-muted last-message">Chargement...</small>
                                </div>
                                <?php if ($group['unread'] > 0): ?>
                                    <span class="badge bg-danger rounded-pill ms-2"><?= $group['unread'] ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
                -->
            </ul>
        </div>
    </div>
    
    <!-- Zone de conversation principale -->
    <div class="chat-main">
        <div class="chat-header" id="chatHeader">
            <div class="d-flex align-items-center">
                <img id="currentChatPhoto" src="assets/images/users/default.png" 
                     class="rounded-circle me-2" width="40" height="40" alt="Photo profil">
                <div>
                    <h5 id="currentChatName">Sélectionnez une conversation</h5>
                    <small class="text-muted" id="currentChatStatus"></small>
                </div>
            </div>
            <div class="chat-actions">
                <button class="btn btn-sm btn-outline-secondary" id="chatInfoBtn" disabled>
                    <i class="fas fa-info-circle"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" id="clearChatBtn" disabled>
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        
        <!-- Messages -->
        <div class="chat-messages" id="chatMessages">
            <div class="empty-state">
                <i class="fas fa-comment-dots fa-3x text-muted mb-3"></i>
                <p>Sélectionnez une conversation pour commencer à discuter</p>
            </div>
        </div>
        
        <!-- Zone de saisie -->
        <div class="chat-input">
            <div class="chat-tools">
                <button class="btn btn-sm btn-outline-secondary" id="attachFileBtn">
                    <i class="fas fa-paperclip"></i>
                    <input type="file" id="fileInput" style="display: none;">
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="recordAudioBtn">
                    <i class="fas fa-microphone"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="emojiBtn">
                    <i class="far fa-smile"></i>
                </button>
            </div>
            <textarea id="messageInput" placeholder="Écrivez votre message..." rows="1"></textarea>
            <button class="btn btn-primary" id="sendMessageBtn" disabled>
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<!-- Modal pour nouveau chat -->
<div class="modal fade" id="newChatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouvelle conversation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Rechercher un utilisateur</label>
                    <input type="text" class="form-control" id="userSearchInput" placeholder="Nom, prénom ou email">
                </div>
                
                <div id="userSearchResults" class="list-group" style="max-height: 300px; overflow-y: auto;">
    <?php
    // Chargement initial des utilisateurs
    $users = $pdo->query("SELECT id, first_name, last_name, photo, role_id FROM users WHERE id != $userId LIMIT 10")->fetchAll();
    foreach ($users as $user) {
        echo '<a href="#" class="list-group-item list-group-item-action user-item" data-id="'.$user['id'].'">';
        echo '<div class="d-flex align-items-center">';
        echo '<img src="assets/images/users/'.$user['photo'].'" class="rounded-circle me-2" width="40" height="40">';
        echo '<div><strong>'.$user['first_name'].' '.$user['last_name'].'</strong></div>';
        echo '</div></a>';
    }
    ?>
</div>

            </div>
        </div>
    </div>
</div>

<!-- Modal pour info chat -->
<div class="modal fade" id="chatInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="chatInfoTitle">Informations</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="chatInfoContent">
                <!-- Contenu dynamique -->
            </div>
        </div>
    </div>
</div>

<!-- Template pour un message (utilisé par JavaScript) -->
<template id="messageTemplate">
    <div class="message">
        <div class="message-avatar">
            <img src="" alt="Photo profil" class="rounded-circle">
        </div>
        <div class="message-content">
            <div class="message-header">
                <strong class="message-sender"></strong>
                <small class="message-time text-muted"></small>
            </div>
            <div class="message-body"></div>
            <div class="message-actions">
                <button class="btn btn-sm btn-link reply-btn" title="Répondre">
                    <i class="fas fa-reply"></i>
                </button>
                <button class="btn btn-sm btn-link delete-btn" title="Supprimer">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<!-- Template pour un message de l'utilisateur courant -->
<template id="myMessageTemplate">
    <div class="message me">
        <div class="message-content">
            <div class="message-header">
                <small class="message-time text-muted"></small>
                <strong class="message-sender">Moi</strong>
            </div>
            <div class="message-body"></div>
            <div class="message-status">
                <i class="fas fa-check-double read-status"></i>
            </div>
        </div>
        <div class="message-avatar">
            <img src="" alt="Photo profil" class="rounded-circle">
        </div>
    </div>
</template>

<!-- Template pour un fichier joint -->
<template id="fileAttachmentTemplate">
    <div class="file-attachment">
        <div class="file-icon">
            <i class="fas fa-file"></i>
        </div>
        <div class="file-info">
            <a href="#" class="file-name" target="_blank"></a>
            <small class="file-size text-muted"></small>
        </div>
    </div>
</template>

<!-- Scripts JavaScript -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/moment.min.js"></script>
<script src="assets/js/moment-locale-fr.js"></script>
<script>
// Configuration de base
let currentChat = {
    type: null, // 'private' ou 'group'
    id: null,
    name: null,
    photo: null
};

let socket = null;
const userId = <?= $userId ?>;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser moment.js en français
    moment.locale('fr');
    
    // Gestion des clics sur les conversations
    document.querySelectorAll('.chat-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            loadChat(
                this.dataset.type, 
                this.dataset.id, 
                this.querySelector('strong').textContent,
                this.querySelector('img').src
            );
        });
    });
    
    // Nouvelle conversation
    document.getElementById('newChatBtn').addEventListener('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('newChatModal'));
    modal.show();
    
    // Reset et focus sur le champ de recherche
    document.getElementById('userSearchInput').value = '';
    document.getElementById('userSearchResults').innerHTML = '';
    document.getElementById('userSearchInput').focus();
});
    
    // Recherche d'utilisateurs
    document.getElementById('userSearchInput').addEventListener('input', function() {
        const query = this.value.trim();
        if (query.length < 2) {
            document.getElementById('userSearchResults').innerHTML = '';
            return;
        }
        
        fetch(`api/search_users.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(users => {
                const resultsContainer = document.getElementById('userSearchResults');
                resultsContainer.innerHTML = '';
                
                if (users.length === 0) {
                    resultsContainer.innerHTML = '<div class="list-group-item text-muted">Aucun résultat</div>';
                    return;
                }
                
                users.forEach(user => {
                    if (user.id === userId) return; // Ne pas s'envoyer de message à soi-même
                    
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action';
                    item.innerHTML = `
                        <div class="d-flex align-items-center">
                            <img src="assets/images/users/${user.photo}" 
                                 class="rounded-circle me-2" width="40" height="40" alt="Photo profil">
                            <div>
                                <strong>${user.first_name} ${user.last_name}</strong>
                                <small class="d-block text-muted">${user.role_name}</small>
                            </div>
                        </div>
                    `;
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        loadChat('private', user.id, `${user.first_name} ${user.last_name}`, `assets/images/users/${user.photo}`);
                        modal.hide();
                    });
                    
                    resultsContainer.appendChild(item);
                });
            })
            .catch(error => {
                console.error('Erreur recherche:', error);
            });
    });

    // Recherche d'utilisateurs améliorée
document.getElementById('userSearchInput').addEventListener('input', function() {
    const query = this.value.trim();
    const resultsContainer = document.getElementById('userSearchResults');
    
    if (query.length < 2) {
        resultsContainer.innerHTML = '';
        return;
    }

    fetch(`api/search_users.php?q=${encodeURIComponent(query)}&current_user=${userId}`)
        .then(response => response.json())
        .then(users => {
            resultsContainer.innerHTML = '';
            
            if (users.length === 0) {
                resultsContainer.innerHTML = '<div class="list-group-item text-muted">Aucun résultat</div>';
                return;
            }
            
            users.forEach(user => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action user-item';
                item.dataset.id = user.id;
                item.innerHTML = `
                    <div class="d-flex align-items-center">
                        <img src="assets/images/users/${user.photo}" 
                             class="rounded-circle me-2" width="40" height="40">
                        <div>
                            <strong>${user.first_name} ${user.last_name}</strong>
                            <small class="d-block text-muted">${user.role_name || ''}</small>
                        </div>
                    </div>
                `;
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    loadChat('private', user.id, `${user.first_name} ${user.last_name}`, `assets/images/users/${user.photo}`);
                    bootstrap.Modal.getInstance(document.getElementById('newChatModal')).hide();
                });
                resultsContainer.appendChild(item);
            });
        });
});
    
    // Envoi de message
    document.getElementById('sendMessageBtn').addEventListener('click', sendMessage);
    document.getElementById('messageInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    // Pièce jointe
    document.getElementById('attachFileBtn').addEventListener('click', function() {
        document.getElementById('fileInput').click();
    });
    
    document.getElementById('fileInput').addEventListener('change', function() {
        if (this.files.length > 0) {
            uploadFile(this.files[0]);
        }
    });
    
    // Connexion WebSocket
    connectWebSocket();
});

// Charger une conversation
function loadChat(type, id, name, photo) {
    currentChat = { type, id, name, photo };
    
    // Mettre à jour l'en-tête
    document.getElementById('currentChatName').textContent = name;
    document.getElementById('currentChatPhoto').src = photo;
    document.getElementById('currentChatStatus').textContent = type === 'private' ? 'En ligne' : `${countMembers(id)} membres`;
    
    // Activer les boutons
    document.getElementById('chatInfoBtn').disabled = false;
    document.getElementById('clearChatBtn').disabled = false;
    document.getElementById('sendMessageBtn').disabled = false;
    
    // Charger les messages
    fetch(`api/get_messages.php?type=${type}&id=${id}`)
        .then(response => response.json())
        .then(messages => {
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.innerHTML = '';
            
            if (messages.length === 0) {
                messagesContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                        <p>Aucun message dans cette conversation</p>
                        <p class="text-muted">Envoyez le premier message !</p>
                    </div>
                `;
                return;
            }
            
            messages.forEach(message => {
                addMessageToChat(message, false);
            });
            
            // Faire défiler vers le bas
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // Marquer comme lus
            markMessagesAsRead();
        })
        .catch(error => {
            console.error('Erreur chargement messages:', error);
        });
    
    // Pour mobile: afficher seulement la zone de chat
    if (window.innerWidth < 768) {
        document.querySelector('.chat-sidebar').classList.remove('active');
        document.querySelector('.chat-main').classList.add('active');
    }
}

// Ajouter un message à la conversation
function addMessageToChat(message, scroll = true) {
    if ((message.sender_id == userId && message.recipient_id == currentChat.id) || 
        (message.recipient_id == userId && message.sender_id == currentChat.id) ||
        (message.group_id && message.group_id == currentChat.id)) {
        
        const messagesContainer = document.getElementById('chatMessages');
        const isMe = message.sender_id == userId;
        const template = isMe ? document.getElementById('myMessageTemplate') : document.getElementById('messageTemplate');
        const clone = template.content.cloneNode(true);
        
        const messageElement = clone.querySelector('.message');
        if (isMe) {
            // Message envoyé par l'utilisateur
            clone.querySelector('.message-body').innerHTML = message.content;
            clone.querySelector('.message-time').textContent = moment(message.created_at).format('LT');
            
            // Statut de lecture
            if (message.is_read) {
                clone.querySelector('.read-status').classList.add('text-primary');
            }
        } else {
            // Message reçu
            clone.querySelector('.message-sender').textContent = message.sender_name;
            clone.querySelector('.message-body').innerHTML = message.content;
            clone.querySelector('.message-time').textContent = moment(message.created_at).format('LT');
            clone.querySelector('img').src = `assets/images/users/${message.sender_photo}`;
        }
        
        messagesContainer.appendChild(clone);
        
        if (scroll) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }
}

// Envoyer un message
function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (message === '' || !currentChat.id) return;
    
    // Créer l'objet message
    const newMessage = {
        type: currentChat.type,
        id: currentChat.id,
        content: message,
        sender_id: userId,
        sender_name: '<?= $currentUser['first_name'] . ' ' . $currentUser['last_name'] ?>',
        sender_photo: '<?= $currentUser['photo'] ?>',
        created_at: new Date().toISOString()
    };
    
    // Envoyer via WebSocket
    if (socket && socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify({
            action: 'send_message',
            data: newMessage
        }));
    } else {
        // Fallback AJAX si WebSocket indisponible
        fetch('api/send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(newMessage)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                addMessageToChat(data.message);
            }
        });
    }
    
    // Ajouter immédiatement à l'interface (optimistic update)
    addMessageToChat(newMessage);
    
    // Réinitialiser l'input
    input.value = '';
    input.focus();
}

// Upload de fichier
function uploadFile(file) {
    if (!currentChat.id) return;
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', currentChat.type);
    formData.append('chat_id', currentChat.id);
    
    fetch('api/upload_file.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Créer le message avec le fichier
            const fileMessage = {
                content: `<div class="file-message">
                    <i class="fas fa-file"></i> 
                    <a href="uploads/${data.file_path}" target="_blank">${data.file_name}</a>
                    (${formatFileSize(data.file_size)})
                </div>`,
                file_info: data
            };
            
            // Envoyer comme un message normal
            sendMessage(fileMessage);
        }
    })
    .catch(error => {
        console.error('Erreur upload:', error);
    });
}

// Marquer les messages comme lus
function markMessagesAsRead() {
    if (!currentChat.id) return;
    
    fetch('api/mark_as_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            type: currentChat.type,
            chat_id: currentChat.id
        })
    });
    
    // Mettre à jour le compteur dans la sidebar
    document.querySelectorAll('.chat-link').forEach(link => {
        if ((link.dataset.type === currentChat.type) && (link.dataset.id == currentChat.id)) {
            const badge = link.querySelector('.badge');
            if (badge) {
                badge.remove();
            }
        }
    });
}

// Connexion WebSocket
function connectWebSocket() {
    const protocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
    const host = window.location.host;
    const path = '/ws_chat.php';
    
    socket = new WebSocket(protocol + host + path + `?user_id=${userId}`);
    
    socket.onopen = function() {
        console.log('Connexion WebSocket établie');
    };
    
    socket.onmessage = function(event) {
        const data = JSON.parse(event.data);
        
        switch (data.action) {
            case 'new_message':
                addMessageToChat(data.message);
                break;
                
            case 'message_read':
                updateReadStatus(data.message_id);
                break;
                
            case 'user_online':
                updateUserStatus(data.user_id, true);
                break;
                
            case 'user_offline':
                updateUserStatus(data.user_id, false);
                break;
        }
    };
    
    socket.onclose = function() {
        console.log('Connexion WebSocket fermée, tentative de reconnexion...');
        setTimeout(connectWebSocket, 5000);
    };
    
    socket.onerror = function(error) {
        console.error('Erreur WebSocket:', error);
    };
}

// Fonctions utilitaires
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function countMembers(groupId) {
    // Implémenter pour les groupes
    return 0;
}

function updateReadStatus(messageId) {
    document.querySelectorAll('.message').forEach(message => {
        if (message.dataset.id === messageId) {
            const statusIcon = message.querySelector('.read-status');
            if (statusIcon) {
                statusIcon.classList.add('text-primary');
            }
        }
    });
}

function updateUserStatus(userId, isOnline) {
    document.querySelectorAll('.chat-link').forEach(link => {
        if (link.dataset.id == userId) {
            const statusElement = link.querySelector('.user-status');
            if (statusElement) {
                statusElement.textContent = isOnline ? 'En ligne' : 'Hors ligne';
                statusElement.className = isOnline ? 'user-status text-success' : 'user-status text-muted';
            }
        }
    });
}
</script>

<?php
include_once 'includes/footer.php';