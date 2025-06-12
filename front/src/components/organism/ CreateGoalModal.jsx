import React, { useState, useEffect, useRef } from "react";
import { Modal as BootstrapModal } from "bootstrap";
import { Button, Title, CheckBox } from "../atoms";

const CreateGoalModal = ({ show, onHide, onSave }) => {
    const modalRef = useRef(null);
    const bsModal = useRef(null);

    const [formData, setFormData] = useState({
        title: "",
        description: "",
        frequencyType: "daily",
    });
    const [errors, setErrors] = useState({});

    useEffect(() => {
        if (modalRef.current) {
            bsModal.current = new BootstrapModal(modalRef.current, {
                backdrop: "static",
                keyboard: false,
            });
        }
    }, []);

    useEffect(() => {
        if (bsModal.current) {
            show ? bsModal.current.show() : bsModal.current.hide();
        }
    }, [show]);

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData({
            ...formData,
            [name]: value,
        });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (formData.title.trim() === "") {
            setErrors({ title: "Le titre est requis." });
            return;
        }

        try {
            const response = await fetch("/api/v1/goals", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(formData),
            });

            if (!response.ok) {
                const errorData = await response.json();
                // Exemple : affichage d'un message d'erreur venant du backend
                setErrors({
                    api: errorData.message || "Une erreur est survenue.",
                });
                return;
            }

            const savedGoal = await response.json();
            onSave(savedGoal); // callback vers le parent si besoin
            setFormData({ title: "", description: "", frequencyType: "daily" });
            setErrors({});
            onHide();
            console.log("Goal created successfully:", savedGoal);
        } catch (error) {
            setErrors({ api: "Erreur de réseau ou du serveur." });
            console.error("Error creating goal:", error);
        }
    };

    return (
        <div
            className="modal fade"
            tabIndex="-1"
            ref={modalRef}
            aria-hidden="true"
        >
            <div className="modal-dialog modal-lg">
                <div className="modal-content">
                    <div className="modal-header">
                        <h5 className="modal-title">Créer un Objectif</h5>
                        <Button
                            type="button"
                            className="btn-close"
                            onClick={onHide}
                        ></Button>
                    </div>

                    <form onSubmit={handleSubmit}>
                        <div className="modal-body">
                            {/* Titre */}
                            <div className="mb-3 me-3">
                                <Title className="form-label">
                                    Nom de l'Objectif
                                    <span className="text-danger">*</span>
                                </Title>
                                <input
                                    type="text"
                                    className={`form-control ${
                                        errors.title ? "is-invalid" : ""
                                    }`}
                                    id="title"
                                    name="title"
                                    value={formData.title}
                                    onChange={handleChange}
                                    placeholder="Ex: Faire du sport, Lire un livre..."
                                    maxLength="255"
                                />
                                {errors.title && (
                                    <div className="invalid-feedback">
                                        {errors.title}
                                    </div>
                                )}
                            </div>

                            {/* Description */}
                            <div className="mb-3">
                                <Title className="form-label">
                                    Description
                                </Title>
                                <textarea
                                    className="form-control"
                                    id="description"
                                    name="description"
                                    value={formData.description}
                                    onChange={handleChange}
                                    rows="3"
                                    maxLength="1000"
                                ></textarea>
                                <div className="form-text">
                                    {formData.description.length}/1000
                                    caractères
                                </div>
                            </div>

                            {/* Type de fréquence */}
                            <Title className="form-label">
                                Type de fréquence
                                <span className="text-danger">*</span>
                            </Title>

                            <CheckBox
                                id="frequency-daily"
                                value="daily"
                                name="frequencyType"
                                checked={formData.frequencyType === "daily"}
                                onChange={handleChange}
                                optionName="Quotidienne"
                            />

                            <CheckBox
                                id="frequency-weekly"
                                value="weekly"
                                name="frequencyType"
                                checked={formData.frequencyType === "weekly"}
                                onChange={handleChange}
                                optionName="Hebdomadaire"
                            />
                            <CheckBox
                                id="frequency-monthly"
                                value="monthly"
                                name="frequencyType"
                                checked={formData.frequencyType === "monthly"}
                                onChange={handleChange}
                                optionName="Mensuelle"
                            />

                        </div>

                        <div className="modal-footer">
                            <Button
                                type="button"
                                className="btn btn-secondary"
                                onClick={onHide}
                            >
                                <Title> Annuler </Title>
                            </Button>
                            <Button
                                type="submit"
                                className="btn btn-dark"
                                onClick={handleSubmit}
                            >
                                <Title> Créer l'Objectif </Title>
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
};

export default CreateGoalModal;
