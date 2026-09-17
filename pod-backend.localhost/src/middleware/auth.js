function verifySecret(req, res, next) {
    const secret = req.headers['x-pod-secret'] || req.query.secret || (req.body && req.body.secret);
    const expectedSecret = process.env.SHARED_SECRET || 'pod_secret_token_123456';

    if (!secret || secret !== expectedSecret) {
        return res.status(401).json({
            success: false,
            error: 'Unauthorized: Invalid or missing secret token (X-POD-SECRET)'
        });
    }

    next();
}

module.exports = { verifySecret };
