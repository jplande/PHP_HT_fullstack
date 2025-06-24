import React, { useState, useRef } from "react";
import { Button, Title, CheckBox } from "../atoms";

const GoalForm = ({
    handleSubmit,
    submitLabel,
    onHide,
    formData,
    setFormData,
    errors,
}) => {

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData({ ...formData, [name]: value });
    };

    return (
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
                        <div className="invalid-feedback">{errors.title}</div>
                    )}
                </div>

                {/* Description */}
                <div className="mb-3">
                    <Title className="form-label">Description</Title>
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
                        {formData.description.length}/1000 caractères
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
                    <Title> {submitLabel} </Title>
                </Button>
            </div>
        </form>
    );
};

export default GoalForm;
