function verifySecret(req, res, next) {
    const secret = req.headers['x-pod-secret'];
    const expectedSecret = process.env.SHARED_SECRET || 'pod_secret_token_123456';

    if (!secret || secret !== expectedSecret) {
        return res.status(401).json({
            success: false,
            error: 'Unauthorized: Invalid or missing X-POD-SECRET header'
        });
    }

    next();
}

module.exports = { verifySecret };
