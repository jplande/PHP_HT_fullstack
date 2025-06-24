import React, { useState, useEffect, useRef } from "react";
import { Modal as BootstrapModal } from "bootstrap";
import GoalForm from "./GoalForm";

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
        if (show) {
            bsModal.current.show();
        } else {
            bsModal.current.hide();

            // retirer le focus sur la modale pour correctement fermer la modale et eviter le warning
            setTimeout(() => {
                if (document.activeElement instanceof HTMLElement) {
                    document.activeElement.blur();
                }
            }, 50);
        }
    }
}, [show]);



    const handleSubmit = async (e) => {
        e.preventDefault();

        if (formData.title.trim() === "") {
            setErrors({ title: "Le titre est requis." });
            return;
        }

        try {
            const response = await fetch("/api/v1/goals", {
                method: "PUT",
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
                    <GoalForm
                        formData={formData}
                        setFormData={setFormData}
                        errors={errors}
                        handleSubmit={handleSubmit}
                        submitLabel="Modifier l'objectif"
                        onHide={onHide}
                    />
                </div>
            </div>
        </div>
    );
};

export default CreateGoalModal;
