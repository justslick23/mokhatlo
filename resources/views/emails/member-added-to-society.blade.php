<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome to {{ $society->name }}</title>
</head>
<body>
    <h1>Welcome to {{ $society->name }}</h1>
    
    <p>Hi {{ $user->name }},</p>
    
    <p>You have been added to <strong>{{ $society->name }}</strong> as a <strong>{{ $member->role }}</strong>.</p>
    
    <p>Thank you.</p>
</body>
</html>