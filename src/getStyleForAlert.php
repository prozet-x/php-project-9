<?php
function getStyleForAlert($messagesGroup) {
    if ($messagesGroup === 'success') {
        return 'alert-success';
    };
    if ($messagesGroup === 'warning') {
        return 'alert-info';
    };
    return 'alert-danger';
}