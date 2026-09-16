const express = require('express');
const router = express.Router();
const sharpRenderer = require('../services/sharpRenderer');
const { verifySecret } = require('../middleware/auth');
const axios = require('axios');

/**
 * POST /api/v1/render
 * Initiates render task for a custom order item.
 */
router.post('/render', verifySecret, async (req, res) => {
    try {
        const payload = req.body;
        if (!payload || !payload.order_id) {
            return res.status(422).json({
                success: false,
                error: 'Strict Data Contract Violation: order_id is required'
            });
        }

        const renderResult = await sharpRenderer.render(payload);

        // Build absolute public download URLs for client/admin/factories
        const baseUrl = process.env.BASE_URL || `${req.protocol}://${req.get('host')}`;
        const absolutePrintUrl = `${baseUrl}${renderResult.url}`;
        const absoluteZipUrl = `${baseUrl}${renderResult.zip_url}`;
        renderResult.public_url = absolutePrintUrl;
        renderResult.public_zip_url = absoluteZipUrl;

        // If a callback URL is provided, notify WordPress asynchronously
        if (payload.callback_url) {
            axios.post(payload.callback_url, {
                order_id: payload.order_id,
                item_id: payload.item_id,
                print_url: absolutePrintUrl,
                zip_url: absoluteZipUrl,
                dpi: 300,
                status: 'completed'
            }, {
                headers: { 'X-POD-SECRET': process.env.SHARED_SECRET }
            }).then(response => {
                // If WordPress confirmed storing files locally, delete temporary files on the render worker
                if (response.data && response.data.stored_locally) {
                    const fs = require('fs');
                    if (renderResult.print_output_path && fs.existsSync(renderResult.print_output_path)) {
                        fs.unlink(renderResult.print_output_path, (err) => {
                            if (err) console.error('[POD Backend] Failed to remove temp print file:', err.message);
                        });
                    }
                    if (renderResult.zip_output_path && fs.existsSync(renderResult.zip_output_path)) {
                        fs.unlink(renderResult.zip_output_path, (err) => {
                            if (err) console.error('[POD Backend] Failed to remove temp zip file:', err.message);
                        });
                    }
                    console.log(`[POD Backend] Order #${payload.order_id} Item #${payload.item_id}: Production files transferred to client. Worker temp storage cleaned.`);
                }
            }).catch(err => {
                console.error('[POD Backend] Failed to dispatch webhook callback to WordPress:', err.message);
            });
        }

        return res.status(200).json({
            success: true,
            data: renderResult
        });
    } catch (error) {
        console.error('[POD Backend] Error during render:', error);
        return res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

module.exports = router;
